<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Integration;

use Contenir\Seo\Mezzio\ConfigProvider;
use Contenir\Seo\Mezzio\Exception\InvalidServiceException;
use Contenir\Seo\Mezzio\SeoHead;
use Contenir\Seo\Mezzio\SeoMiddleware;
use Contenir\Seo\Mezzio\Sitemap\SitemapResponses;
use Contenir\Seo\Mezzio\Tests\TestAsset\FixedPages;
use Contenir\Seo\Mezzio\Tests\TestAsset\FixedStrategy;
use Contenir\Seo\Mezzio\Tests\TestAsset\RouteNameRouter;
use Contenir\Seo\Mezzio\Tests\Trait\TempDirectoryTrait;
use Contenir\Seo\Sitemap\Page;
use Contenir\Workflow\Strategy\ResourceStrategy;
use Laminas\Diactoros\ConfigProvider as DiactorosConfigProvider;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequest;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\HelperPluginManager;
use Laminas\View\Renderer\PhpRenderer;
use Mezzio\Router\RouterInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use stdClass;

use function array_merge_recursive;
use function str_contains;

/**
 * The package in a real service container: the middleware answering the
 * sitemap from the workflow navigation and a site source, and the seo()
 * helper rendering through laminas-view.
 */
#[Group('integration')]
final class ApplicationTest extends TestCase
{
    use TempDirectoryTrait;

    private static function handle(ServiceManager $container, string $url): ResponseInterface
    {
        $handler = new class implements RequestHandlerInterface {
            #[Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new TextResponse('page');
            }
        };

        return $container->get(SeoMiddleware::class)->process(new ServerRequest(uri: $url), $handler);
    }

    #[Test]
    public function aSitemapSourceOfTheWrongTypeIsRefused(): void
    {
        $container = $this->container(['sitemap_sources' => ['not-a-source']]);
        $container->setService('not-a-source', new stdClass());

        $this->expectException(InvalidServiceException::class);

        $container->get(SitemapResponses::class);
    }

    #[Test]
    public function theHomePageIsAtTheRootByDefault(): void
    {
        $container = $this->container();
        self::handle($container, 'https://www.example.test/');

        static::assertNotNull($container->get(SeoHead::class)->build('Home')->jsonLd);
    }

    #[Test]
    public function theNavigationCanBeLeftOutOfTheSitemap(): void
    {
        $body = (string) self::handle(
            $this->container(['sitemap_navigation' => false, 'sitemap_sources' => [FixedPages::class]]),
            'https://www.example.test/sitemap.xml',
        )
            ->getBody();

        static::assertSame([false, true], [
            str_contains($body, '/about</loc>'),
            str_contains($body, '/work/convoy</loc>'),
        ]);
    }

    #[Test]
    public function theSeoHelperRendersTheHeadForTheRecordedRequest(): void
    {
        $container = $this->container(['home_paths' => ['/', 7, '', '/home']]);
        self::handle($container, 'https://www.example.test/home');

        $renderer = new PhpRenderer();
        $renderer->setHelperPluginManager(
            new HelperPluginManager($container, (new ConfigProvider())->getViewHelpers()),
        );
        $html = $renderer->plugin('seo')()->render('Home');

        static::assertSame(
            [true, true],
            [str_contains($html, '<title>Home | Example</title>'), str_contains($html, 'application/ld+json')],
        );
    }

    #[Test]
    public function theSitemapListsTheNavigationAndTheSitesSources(): void
    {
        $body = (string) self::handle(
            $this->container(['sitemap_sources' => [FixedPages::class]]),
            'https://www.example.test/sitemap.xml',
        )
            ->getBody();

        static::assertSame(
            [true, true, true],
            [
                str_contains($body, '<loc>https://www.example.test/home</loc>'),
                str_contains($body, '<loc>https://www.example.test/about</loc>'),
                str_contains($body, '<loc>https://www.example.test/work/convoy</loc>'),
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->makeTempDirectory();
        $this->writeConfig('extensions.global.php', ['extensions' => ['enabled' => ['seo']]]);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function container(array $options = []): ServiceManager
    {
        $container = new ServiceManager(array_merge_recursive(
            (new ConfigProvider())->getDependencies(),
            (new DiactorosConfigProvider())->getDependencies(),
        ));
        $container->setService('config', [
            'site'             => ['url' => 'https://www.example.test', 'title' => 'Example'],
            'extensions'       => ['seo' => ['visibility' => 'show']],
            'workflow_manager' => ['strategy' => ResourceStrategy::class],
            'contenir_seo'     => ['extensions_config' => $this->directory, ...$options],
        ]);
        $container->setService(ResourceStrategy::class, new FixedStrategy(
            [['route' => 'home', 'visible' => false], ['route' => 'about']],
            ['home' => [], 'about' => []],
        ));
        $container->setService(RouterInterface::class, new RouteNameRouter());
        $container->setService(FixedPages::class, new FixedPages([new Page('/work/convoy')]));

        return $container;
    }
}
