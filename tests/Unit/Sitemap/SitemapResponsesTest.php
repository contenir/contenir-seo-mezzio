<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Unit\Sitemap;

use Contenir\Seo\Mezzio\Sitemap\SitemapResponses;
use Contenir\Seo\Mezzio\Tests\TestAsset\FixedPages;
use Contenir\Seo\Mezzio\Tests\TestAsset\SeoSettings;
use Contenir\Seo\SeoConfig;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function str_contains;

#[Group('unit')]
final class SitemapResponsesTest extends TestCase
{
    private static function body(?ResponseInterface $response): string
    {
        static::assertNotNull($response);

        return (string) $response->getBody();
    }

    private static function request(string $url = 'https://www.example.test/sitemap.xml'): ServerRequest
    {
        return new ServerRequest(uri: $url);
    }

    /**
     * @param positive-int $size
     */
    private static function responses(?SeoConfig $config = null, int $pages = 3, int $size = 50_000): SitemapResponses
    {
        return new SitemapResponses(
            $config ?? SeoSettings::config(),
            new ResponseFactory(),
            new StreamFactory(),
            [FixedPages::numbered($pages)],
            $size,
        );
    }

    #[Test]
    public function aDisabledSitemapIsNotFound(): void
    {
        $responses = self::responses(SeoSettings::config(['site' => ['sitemap' => ['enabled' => false]]]), 3, 2);

        static::assertSame([null, null], [$responses->sitemap(self::request()), $responses->page(self::request(), 1)]);
    }

    #[Test]
    public function aLargeSitemapIsAnIndexOfItsNumberedPages(): void
    {
        $responses = self::responses(
            pages: 3,
            size: 2,
        );

        static::assertSame(
            [true, true, true, false],
            [
                str_contains(
                    self::body($responses->sitemap(self::request())),
                    '<loc>https://www.example.test/sitemap-2.xml</loc>',
                ),
                str_contains(self::body($responses->page(self::request(), 1)), '/page-2</loc>'),
                str_contains(self::body($responses->page(self::request(), 2)), '/page-3</loc>'),
                null !== $responses->page(self::request(), 3),
            ],
        );
    }

    #[Test]
    public function aNumberedPageBelowOneIsNotFound(): void
    {
        static::assertNull(
            self::responses(
                pages: 3,
                size: 2,
            )
                ->page(self::request(), 0),
        );
    }

    #[Test]
    public function aSitemapThatIsNotAnIndexHasNoNumberedPages(): void
    {
        static::assertNull(self::responses()->page(self::request(), 1));
    }

    #[Test]
    public function aSmallSitemapListsEveryPageOnTheSiteUrl(): void
    {
        $response = self::responses()->sitemap(self::request());
        $body     = self::body($response);

        static::assertSame(
            ['application/xml; charset=utf-8', true, true, false],
            [
                $response?->getHeaderLine('Content-Type'),
                str_contains($body, '<loc>https://www.example.test/page-1</loc>'),
                str_contains($body, '<loc>https://www.example.test/page-3</loc>'),
                str_contains($body, '<sitemapindex'),
            ],
        );
    }

    #[Test]
    public function robotsTxtNamesTheSitemap(): void
    {
        $response = self::responses()->robots(self::request('https://www.example.test/robots.txt'));

        static::assertSame(
            ['text/plain; charset=utf-8', true, true],
            [
                $response->getHeaderLine('Content-Type'),
                str_contains((string) $response->getBody(), 'Sitemap: https://www.example.test/sitemap.xml'),
                str_contains((string) $response->getBody(), 'Allow: /'),
            ],
        );
    }

    #[Test]
    public function robotsTxtOnAHiddenCopyDisallowsEverything(): void
    {
        $body = (string) self::responses(SeoSettings::config(['extensions' => ['seo' => ['visibility' => 'auto']]]))
            ->robots(self::request('https://staging.example.test/robots.txt'))
            ->getBody();

        static::assertSame([true, false], [str_contains($body, 'Disallow: /'), str_contains($body, 'Sitemap:')]);
    }

    #[Test]
    public function robotsTxtWithTheSitemapDisabledNamesNoSitemap(): void
    {
        $body = (string) self::responses(SeoSettings::config(['site' => ['sitemap' => ['enabled' => false]]]))
            ->robots(self::request('https://www.example.test/robots.txt'))
            ->getBody();

        static::assertStringNotContainsString('Sitemap:', $body);
    }

    #[Test]
    public function withASiteUrlLinksAreOnItWhateverTheRequestsHost(): void
    {
        $body = self::body(self::responses()->sitemap(self::request('http://localhost:8080/sitemap.xml')));

        static::assertStringContainsString('<loc>https://www.example.test/page-1</loc>', $body);
    }

    #[Test]
    public function withoutASiteUrlLinksAreOnTheRequestsOrigin(): void
    {
        $body = self::body(self::responses(SeoSettings::config(['site' => ['url' => '']]))
            ->sitemap(
                self::request('http://localhost:8080/sitemap.xml'),
            ));

        static::assertStringContainsString('<loc>http://localhost:8080/page-1</loc>', $body);
    }
}
