<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Unit;

use Contenir\Seo\Mezzio\CurrentRequest;
use Contenir\Seo\Mezzio\SeoMiddleware;
use Contenir\Seo\Mezzio\Sitemap\SitemapResponses;
use Contenir\Seo\Mezzio\Tests\TestAsset\FixedPages;
use Contenir\Seo\Mezzio\Tests\TestAsset\SeoSettings;
use Contenir\Seo\SeoConfig;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Group('unit')]
final class SeoMiddlewareTest extends TestCase
{
    private CurrentRequest $current;

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function answeredProvider(): array
    {
        return [
            'the sitemap'          => ['GET', '/sitemap.xml', 'application/xml; charset=utf-8'],
            'the sitemap by HEAD'  => ['HEAD', '/sitemap.xml', 'application/xml; charset=utf-8'],
            'robots.txt'           => ['GET', '/robots.txt', 'text/plain; charset=utf-8'],
            'a sitemap index page' => ['GET', '/sitemap-2.xml', 'application/xml; charset=utf-8'],
        ];
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function passedOnProvider(): array
    {
        return [
            'a page'                     => ['GET', '/about', 'page'],
            'a post to the sitemap'      => ['POST', '/sitemap.xml', 'page'],
            'a page the sitemap lacks'   => ['GET', '/sitemap-9.xml', 'page'],
            'a sitemap page numbered 0'  => ['GET', '/sitemap-0.xml', 'page'],
            'a sitemap page with a tail' => ['GET', '/sitemap-1.xml/x', 'page'],
            'a sitemap page in a folder' => ['GET', '/x/sitemap-2.xml', 'page'],
            'robots.txt in a folder'     => ['GET', '/x/robots.txt', 'page'],
        ];
    }

    #[Test]
    public function aHiddenCopysEveryResponseTellsSearchEnginesToStayAway(): void
    {
        $config = SeoSettings::config(['extensions' => ['seo' => ['visibility' => 'auto']]]);

        static::assertSame(
            ['noindex, nofollow', 'noindex, nofollow', ''],
            [
                $this->process('GET', 'https://staging.example.test/about', $config)->getHeaderLine('X-Robots-Tag'),
                $this->process('GET', 'https://staging.example.test/robots.txt', $config)->getHeaderLine(
                    'X-Robots-Tag',
                ),
                $this->process('GET', 'https://www.example.test/about', $config)->getHeaderLine('X-Robots-Tag'),
            ],
        );
    }

    #[Test]
    public function itAnswersANumberedSitemapPageWithThatPage(): void
    {
        static::assertStringContainsString(
            '<loc>https://www.example.test/page-3</loc>',
            (string) $this->process('GET', 'https://www.example.test/sitemap-2.xml')->getBody(),
        );
    }

    #[Test]
    public function itAnswersRobotsTxtFromTheSettings(): void
    {
        static::assertStringContainsString(
            'User-agent: *',
            (string) $this->process('GET', 'https://www.example.test/robots.txt')->getBody(),
        );
    }

    #[Test]
    #[DataProvider('answeredProvider')]
    public function itAnswersTheSitemapAndRobotsTxtItself(string $method, string $path, string $type): void
    {
        static::assertSame(
            $type,
            $this->process($method, "https://www.example.test{$path}")->getHeaderLine('Content-Type'),
        );
    }

    #[Test]
    #[DataProvider('passedOnProvider')]
    public function itPassesEverythingElseOn(string $method, string $path, string $body): void
    {
        static::assertSame($body, (string) $this->process($method, "https://www.example.test{$path}")->getBody());
    }

    #[Test]
    public function itRecordsTheRequestForTheHead(): void
    {
        $this->process('GET', 'https://www.example.test/about');

        static::assertSame('/about', $this->current->get()?->getUri()->getPath());
    }

    protected function setUp(): void
    {
        $this->current = new CurrentRequest();
    }

    private function process(string $method, string $url, ?SeoConfig $config = null): ResponseInterface
    {
        $config  ??= SeoSettings::config();
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willReturnCallback(
                static fn(ServerRequestInterface $request): ResponseInterface => new TextResponse('page'),
            );

        return (new SeoMiddleware(
            $config,
            $this->current,
            new SitemapResponses($config, new ResponseFactory(), new StreamFactory(), [FixedPages::numbered(3)], 2),
        ))->process(new ServerRequest(
            uri: $url,
            method: $method,
        ), $handler);
    }
}
