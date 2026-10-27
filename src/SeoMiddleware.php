<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio;

use Contenir\Seo\Mezzio\Sitemap\SitemapResponses;
use Contenir\Seo\Mezzio\Sitemap\WorkflowNavigationPages;
use Contenir\Seo\Robots\RobotsDirective;
use Contenir\Seo\SeoConfig;
use Contenir\Seo\Visibility\Visibility;
use Laminas\Cache\Exception\ExceptionInterface as CacheException;
use Mezzio\Router\Exception\RuntimeException as RouterException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function in_array;
use function preg_match;

/**
 * The site's SEO for every request:
 *
 * - records the request for SeoHead, which templates call;
 * - answers GET and HEAD /sitemap.xml, /sitemap-N.xml and /robots.txt
 *   (a disabled sitemap, or a page it does not have, goes on down the
 *   pipeline to be not found), so no route is needed, whatever the router;
 * - sends X-Robots-Tag: noindex, nofollow on every response of a copy of the
 *   site that is hidden from search engines, and X-Robots-Tag: noindex,
 *   follow on a page the workflow navigation marks undiscoverable.
 *
 * Pipe it early, before routing and before anything that answers requests
 * itself (such as a page cache), so a hidden copy's every response is marked.
 *
 * @api
 */
final readonly class SeoMiddleware implements MiddlewareInterface
{
    public const string HEADER = 'X-Robots-Tag';

    public function __construct(
        private SeoConfig $config,
        private CurrentRequest $current,
        private SitemapResponses $sitemap,
        private ?WorkflowNavigationPages $navigation = null,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->current->set($request);
        $response = $this->answer($request) ?? $handler->handle($request);

        $robots = $this->robots($request);

        return null === $robots ? $response : $response->withHeader(self::HEADER, $robots->value);
    }

    private function answer(ServerRequestInterface $request): ?ResponseInterface
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], strict: true)) {
            return null;
        }

        $path  = $request->getUri()->getPath();
        $match = [];

        return match (true) {
            SitemapResponses::SITEMAP_PATH === $path => $this->sitemap->sitemap($request),
            SitemapResponses::ROBOTS_PATH === $path => $this->sitemap->robots($request),
            1 === preg_match('~^/sitemap-([1-9][0-9]{0,8})\.xml$~', $path, $match) => $this->sitemap->page(
                $request,
                (int) ($match[1] ?? ''),
            ),
            default => null,
        };
    }

    /**
     * What the response tells search engines, if anything: a hidden copy's every page stays out
     * of search results and its links unfollowed; an undiscoverable page stays out of them alone.
     */
    private function robots(ServerRequestInterface $request): ?RobotsDirective
    {
        $uri = $request->getUri();

        return match (true) {
            $this->config->visibility->hides($this->config->site, $uri->getHost()) => Visibility::HIDDEN,
            in_array($uri->getPath(), $this->undiscoverable(), strict: true) => RobotsDirective::NoindexFollow,
            default => null,
        };
    }

    /**
     * The undiscoverable pages' addresses, or none where the workflow navigation cannot be read:
     * the page then answers as it would without it, and the failure is the router's to report.
     *
     * @return list<string>
     */
    private function undiscoverable(): array
    {
        try {
            return $this->navigation?->undiscoverable() ?? [];
        } catch (CacheException|ContainerExceptionInterface|RouterException) {
            return [];
        }
    }
}
