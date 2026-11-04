<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Inspect;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\Mezzio\PreviewMiddleware;
use Contenir\Preview\Mezzio\TargetMatcher;
use Contenir\Preview\PreviewEntry;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SensitiveParameter;

use function is_array;
use function is_string;
use function json_encode;
use function str_starts_with;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * What search engines and sharing sites would see of a record's page,
 * as the site itself renders it: the CMS posts a preview token, signed as
 * a preview's is, to /api/seo/inspect, and the site renders the record's
 * page with the token's values exactly as a preview does, then answers with
 * the page's head as JSON in place of the page. A token the site refuses is
 * answered with the refusal's status and reason.
 *
 * A page the site dispatches without routing, as a workflow page's preview
 * is, would otherwise render at the endpoint's address and give that as its
 * canonical, share URL and breadcrumbs. The CMS posts the record's public
 * path as `path`, and the page is rendered there; a route or path the site
 * resolves for the record itself takes its place.
 */
final readonly class InspectMiddleware implements MiddlewareInterface
{
    public const string ENDPOINT = '/api/seo/inspect';

    public const string PATH_FIELD = 'path';

    public function __construct(
        private PreviewEntry $entry,
        private TargetMatcher $matcher,
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (self::ENDPOINT !== $request->getUri()->getPath()) {
            return $handler->handle($request);
        }

        try {
            $target  = $this->entry->open($request->getMethod(), $this->token($request));
            $request = $this->matcher->prepare(
                $target,
                $request->withMethod('GET')
                    ->withUri($request->getUri()->withPath($this->path($request)))
                    ->withParsedBody([])
                    ->withHeader('Cache-Control', PreviewMiddleware::REQUEST_CACHE_CONTROL),
            );
        } catch (InvalidPreview $refusal) {
            return $this->json($refusal->status, ['error' => $refusal->getMessage()]);
        }

        $page = $handler->handle($request);

        return $this->json(200, ['status' => $page->getStatusCode(), ...HeadReader::read((string) $page->getBody())]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(int $status, array $body): ResponseInterface
    {
        return $this->responses
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Robots-Tag', 'noindex')
            ->withBody($this->streams->createStream(
                (string) json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ));
    }

    /**
     * The record's public path the CMS posted, or the endpoint's when it posted none: a path
     * on this site only, never another host's address.
     */
    private function path(ServerRequestInterface $request): string
    {
        $body = $request->getParsedBody();
        /** @var mixed $path */
        $path = is_array($body) ? $body[self::PATH_FIELD] ?? null : null;

        return is_string($path) && str_starts_with($path, '/') && ! str_starts_with($path, '//')
            ? $path
            : self::ENDPOINT;
    }

    private function token(#[SensitiveParameter] ServerRequestInterface $request): ?string
    {
        $body = $request->getParsedBody();
        /** @var mixed $token */
        $token = is_array($body) ? $body[PreviewEntry::FIELD] ?? null : null;

        return is_string($token) ? $token : null;
    }
}
