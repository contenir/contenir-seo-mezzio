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

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * What search engines and sharing sites would see of a record's page,
 * as the site itself renders it: the CMS posts a preview token, signed as
 * a preview's is, to /api/seo/inspect, and the site renders the record's
 * page with the token's values exactly as a preview does, then answers with
 * the page's head as JSON in place of the page. A token the site refuses is
 * answered with the refusal's status and reason.
 */
final readonly class InspectMiddleware implements MiddlewareInterface
{
    public const string ENDPOINT = '/api/seo/inspect';

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

    private function token(#[SensitiveParameter] ServerRequestInterface $request): ?string
    {
        $body = $request->getParsedBody();
        /** @var mixed $token */
        $token = is_array($body) ? $body[PreviewEntry::FIELD] ?? null : null;

        return is_string($token) ? $token : null;
    }
}
