<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Integration;

use Contenir\Resource\Core\Metadata\PageMetadataBuilder;
use Contenir\Resource\Core\ResourceManagerInterface;
use Contenir\Seo\Mezzio\ConfigProvider;
use Contenir\Seo\Mezzio\Exception\InvalidServiceException;
use Contenir\Seo\Mezzio\SeoHead;
use Contenir\Seo\Mezzio\SeoMiddleware;
use Contenir\Seo\Mezzio\Sitemap\SitemapResponses;
use Contenir\Seo\Mezzio\Tests\TestAsset\FixedPages;
use Contenir\Seo\Mezzio\Tests\TestAsset\FixedStrategy;
use Contenir\Seo\Mezzio\Tests\TestAsset\ImagedProject;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use stdClass;

use function array_column;
use function array_merge_recursive;
use function str_contains;
use function substr_count;

/**
 * The package in a real service container: the middleware answering the
 * sitemap from the workflow navigation and a site source, and the seo()
 * helper rendering through laminas-view.
 */
#[Group('integration')]
final class ApplicationTest extends TestCase
{
    use TempDirectoryTrait;

    /**
     * @return array<string, array{array<string, mixed>, ?string, int}>
     */
    public static function sitemapImagesProvider(): array
    {
        return [
            'from the site\'s records'        => [[], '/asset/about.jpg', 1],
            'left out when switched off'      => [['sitemap_images' => false], '/asset/about.jpg', 0],
            'left out without the records'    => [['records' => false], '/asset/about.jpg', 0],
            'none without a resource manager' => [[], null, 0],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function trailProvider(): array
    {
        return [
            'from the navigation'                     => [[], 'About us'],
            'from the path when the sitemap skips it' => [['sitemap_navigation' => false], 'About'],
            'from the path without the records'       => [['records' => false], 'About'],
        ];
    }

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
    public function anUndiscoverablePageTellsSearchEnginesToLeaveItOut(): void
    {
        $container = $this->container();

        static::assertSame(
            ['noindex, follow', ''],
            [
                self::handle($container, 'https://www.example.test/thanks')->getHeaderLine('X-Robots-Tag'),
                self::handle($container, 'https://www.example.test/about')->getHeaderLine('X-Robots-Tag'),
            ],
        );
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
    public function noPageIsLeftOutOfSearchWhenTheSitemapSkipsTheNavigation(): void
    {
        static::assertSame(
            '',
            self::handle(
                $this->container(['sitemap_navigation' => false]),
                'https://www.example.test/thanks',
            )
                ->getHeaderLine('X-Robots-Tag'),
        );
    }

    #[Test]
    public function theHomePageIsAtTheRootByDefault(): void
    {
        $container = $this->container();
        self::handle($container, 'https://www.example.test/');

        static::assertSame(
            ['Organization', 'WebSite'],
            array_column($container->get(SeoHead::class)->build('Home')->jsonLd, '@type'),
        );
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

    /**
     * @param array<string, mixed> $options
     */
    #[Test]
    #[DataProvider('trailProvider')]
    public function thePagesTrailIsNamed(array $options, string $name): void
    {
        $container = $this->container($options);
        self::handle($container, 'https://www.example.test/about/team');

        static::assertSame(
            ['Example', $name, 'Team'],
            array_column($container->get(SeoHead::class)->build('Team')->jsonLd[0]['itemListElement'] ?? [], 'name'),
        );
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
            [
                str_contains($html, '<title>Home | Example</title>'),
                str_contains($renderer->plugin('jsonLd')()->script(), '"@type":"WebSite"'),
            ],
        );
    }

    /**
     * @param array<string, mixed> $options
     * @param ?string $image The record's share image, or null for a site without a resource manager
     */
    #[Test]
    #[DataProvider('sitemapImagesProvider')]
    public function theSitemapListsEachPagesShareImage(array $options, ?string $image, int $listed): void
    {
        $container = $this->container($options);
        if (null !== $image) {
            $about        = new ImagedProject();
            $about->image = $image;
            $resources    = $this->createStub(ResourceManagerInterface::class);
            $resources->method('findActive')->willReturn($about);
            $container->setService(ResourceManagerInterface::class, $resources);
            $container->setService(PageMetadataBuilder::class, new PageMetadataBuilder());
        }

        static::assertSame($listed, substr_count(
            (string) self::handle($container, 'https://www.example.test/sitemap.xml')->getBody(),
            needle: '<image:loc>https://www.example.test/asset/about.jpg</image:loc>',
        ));
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
            [true, true, true, false],
            [
                str_contains($body, '<loc>https://www.example.test/home</loc>'),
                str_contains($body, '<loc>https://www.example.test/about</loc>'),
                str_contains($body, '<loc>https://www.example.test/work/convoy</loc>'),
                str_contains($body, '/thanks</loc>'),
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
            'contenir_seo'     => ['extensions_config' => $this->directory, 'records' => true, ...$options],
        ]);
        $container->setService(ResourceStrategy::class, new FixedStrategy(
            [
                ['route' => 'home'],
                ['route' => 'about', 'label' => 'About us'],
                ['route' => 'thanks', 'visible' => false],
            ],
            ['home' => [], 'about' => ['options' => ['defaults' => ['id' => ['resourceId' => 2]]]], 'thanks' => []],
        ));
        $container->setService(RouterInterface::class, new RouteNameRouter());
        $container->setService(FixedPages::class, new FixedPages([new Page('/work/convoy')]));

        return $container;
    }
}
