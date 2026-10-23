<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Sitemap;

use Contenir\Seo\Mezzio\SitemapSourceInterface;
use Contenir\Seo\Robots\RobotsTxt;
use Contenir\Seo\SeoConfig;
use Contenir\Seo\Sitemap\Sitemap;
use Contenir\Seo\Sitemap\SitemapFilter;
use Contenir\Seo\Sitemap\SitemapWriter;
use Contenir\Seo\SiteUrl;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function sprintf;

/**
 * /sitemap.xml, its numbered pages once it is an index, and /robots.txt,
 * from the site's SEO settings. Links are built on the configured site URL,
 * or on the request's origin when there is none. With the sitemap disabled,
 * its URLs are not answered and robots.txt does not name it.
 *
 * @api
 */
final readonly class SitemapResponses
{
    public const string SITEMAP_PATH = '/sitemap.xml';

    public const string PAGE_PATH = '/sitemap-%d.xml';

    public const string ROBOTS_PATH = '/robots.txt';

    /**
     * @param list<SitemapSourceInterface> $sources Where the sitemap's pages come from
     * @param positive-int                 $size    The most URLs in one file
     */
    public function __construct(
        private SeoConfig $config,
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
        private array $sources = [],
        private int $size = Sitemap::MAX_URLS,
    ) {}

    /**
     * One page of a sitemap index; null when the sitemap is disabled, not an
     * index, or has no such page.
     */
    public function page(ServerRequestInterface $request, int $number): ?ResponseInterface
    {
        if (! $this->config->sitemap->enabled) {
            return null;
        }

        $sitemap = $this->build($this->site($request));
        if (! $sitemap->isIndex() || $number < 1 || $number > $sitemap->pageCount()) {
            return null;
        }

        return $this->respond('application/xml; charset=utf-8', (new SitemapWriter())->urlset($sitemap->page($number)));
    }

    public function robots(ServerRequestInterface $request): ResponseInterface
    {
        $site = $this->site($request);

        return $this->respond(
            'text/plain; charset=utf-8',
            (new RobotsTxt(
                $site->origin,
                $this->config->visibility->hides($this->config->site, $request->getUri()->getHost()),
                $this->config->sitemap->enabled ? $site->origin . self::SITEMAP_PATH : null,
                $this->config->robotsRules,
            ))->render(),
        );
    }

    /**
     * The sitemap, or its index past one file; null when it is disabled.
     */
    public function sitemap(ServerRequestInterface $request): ?ResponseInterface
    {
        if (! $this->config->sitemap->enabled) {
            return null;
        }

        $site    = $this->site($request);
        $sitemap = $this->build($site);
        $writer  = new SitemapWriter();

        return $this->respond(
            'application/xml; charset=utf-8',
            $sitemap->isIndex()
                ? $writer->index($sitemap->references($site->origin . self::PAGE_PATH))
                : $writer->urlset($sitemap->page(1)),
        );
    }

    private function build(SiteUrl $site): Sitemap
    {
        $pages = [];
        foreach ($this->sources as $source) {
            foreach ($source->pages() as $page) {
                $pages[] = $page;
            }
        }

        return new Sitemap(
            $pages,
            new SitemapFilter($site, $this->config->sitemap, $this->config->robots),
            $this->size,
        );
    }

    private function respond(string $contentType, string $body): ResponseInterface
    {
        return $this->responses
            ->createResponse()
            ->withHeader('Content-Type', $contentType)
            ->withBody($this->streams->createStream($body));
    }

    /**
     * The configured site URL, or else the request's scheme and host, which a
     * client can spoof: set the site URL on every live site.
     */
    private function site(ServerRequestInterface $request): SiteUrl
    {
        if ($this->config->site->isSet()) {
            return $this->config->site;
        }

        $uri = $request->getUri();

        return new SiteUrl(sprintf('%s://%s', $uri->getScheme(), $uri->getAuthority()));
    }
}
