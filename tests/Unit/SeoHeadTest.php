<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Unit;

use Contenir\Resource\Core\Metadata\PageMetadata;
use Contenir\Seo\Head\Article;
use Contenir\Seo\JsonLd\Graph;
use Contenir\Seo\Mezzio\CurrentRequest;
use Contenir\Seo\Mezzio\SeoHead;
use Contenir\Seo\Mezzio\Sitemap\WorkflowNavigationPages;
use Contenir\Seo\Mezzio\Tests\TestAsset\FixedStrategy;
use Contenir\Seo\Mezzio\Tests\TestAsset\RouteNameRouter;
use Contenir\Seo\Mezzio\Tests\TestAsset\SeoSettings;
use Contenir\Seo\Mezzio\Tests\TestAsset\UnroutableRouter;
use Contenir\Seo\Mezzio\View\JsonLdHelper;
use Contenir\Seo\Mezzio\View\SeoHelper;
use Contenir\Seo\SeoConfig;
use DateTimeImmutable;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Router\RouterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_map;
use function in_array;
use function str_contains;

#[Group('unit')]
final class SeoHeadTest extends TestCase
{
    /**
     * @return array<string, array{RouterInterface, list<string>}>
     */
    public static function trailNamesProvider(): array
    {
        return [
            'named as the navigation labels the pages'   => [new RouteNameRouter(), ['Example', 'Our work', 'Convoy']],
            'named from the path when it cannot be read' => [new UnroutableRouter(), ['Example', 'Work', 'Convoy']],
        ];
    }

    private static function head(string $url, ?SeoConfig $config = null, array $homePaths = ['/']): SeoHead
    {
        $current = new CurrentRequest();
        $current->set(new ServerRequest(uri: $url));

        return new SeoHead($config ?? SeoSettings::config(), $current, $homePaths);
    }

    /**
     * @return list<string>
     */
    private static function keys(SeoHead $head, bool $hasDescription = false, bool $hasImage = false): array
    {
        return array_map(
            static fn($tag): string => $tag->key,
            $head->build('About', $hasDescription, $hasImage)->tags,
        );
    }

    #[Test]
    public function aCopyOnAnotherHostIsMarkedNotToBeIndexed(): void
    {
        $config = SeoSettings::config(['extensions' => ['seo' => ['visibility' => 'auto']]]);

        static::assertSame([true, false], [
            self::head('https://staging.example.test/about', $config)->build('About')->hidden,
            self::head('https://www.example.test/about', $config)->build('About')->hidden,
        ]);
    }

    #[Test]
    public function anArticleIsDescribedAlongsideItsTrail(): void
    {
        $head = self::head('https://www.example.test/work/a-project')
            ->build(
                'A project',
                article: new Article('https://www.example.test/work/a-project', 'A project'),
            );

        static::assertSame(['Article', 'BreadcrumbList'], array_column($head->jsonLd, '@type'));
    }

    #[Test]
    public function anArticleWithoutASchemaTypeIsAnArticle(): void
    {
        static::assertSame(
            'Article',
            SeoHead::mainNode(
                new PageMetadata('https://www.example.test/news', title: 'News', type: PageMetadata::ARTICLE),
            )
                ?->type,
        );
    }

    #[Test]
    public function aPageTellsTheHeadItsTitleAndWhatItWritesItself(): void
    {
        $config = SeoSettings::config([
            'extensions' => ['seo' => ['description' => 'Default', 'share_image' => '/share.jpg']],
        ]);
        $own = self::head('https://www.example.test/about', $config)
            ->renderPage(new PageMetadata(
                'https://www.example.test/about',
                title: 'About',
                description: 'Ours.',
                image: '/ours.jpg',
            ));
        $none = self::head('https://www.example.test/contact', $config)->renderPage(null, 'Contact');

        static::assertSame(
            [true, false, false, true, true],
            [
                str_contains($own, '<title>About | Example</title>'),
                str_contains($own, 'Default'),
                str_contains($own, '/share.jpg'),
                str_contains($none, '<title>Contact | Example</title>'),
                str_contains($none, 'Default'),
            ],
        );
    }

    #[Test]
    public function aPageTitleFollowsTheSitesTemplate(): void
    {
        static::assertSame('About | Example', self::head('https://www.example.test/about')->build('About')->title);
    }

    #[Test]
    public function aPageWithASchemaTypeIsDescribedAsThatType(): void
    {
        $head = self::head('https://www.example.test/work/a-project');
        $head->renderPage(new PageMetadata(
            'https://www.example.test/work/a-project',
            title: 'A project',
            schema: 'CreativeWork',
        ));

        static::assertSame(['CreativeWork', 'BreadcrumbList'], array_column($head->graph()->nodes(), '@type'));
    }

    #[Test]
    public function aPageWithoutATypeOrATitleHasNoMainNode(): void
    {
        static::assertSame(
            [null, null],
            [
                SeoHead::mainNode(new PageMetadata('https://www.example.test/a', title: 'A')),
                SeoHead::mainNode(new PageMetadata('https://www.example.test/a', schema: 'WebPage')),
            ],
        );
    }

    #[Test]
    public function aSchemaTypeWinsOverTheArticleOgType(): void
    {
        static::assertSame(
            'NewsArticle',
            SeoHead::mainNode(new PageMetadata(
                'https://www.example.test/news',
                title: 'News',
                type: PageMetadata::ARTICLE,
                schema: 'NewsArticle',
            ))?->type,
        );
    }

    #[Test]
    public function byDefaultThePageIsGivenTheSitesDescriptionAndShareImage(): void
    {
        $head = self::head(
            'https://www.example.test/about',
            SeoSettings::config([
                'extensions' => ['seo' => ['description' => 'Default', 'share_image' => '/share.jpg']],
            ]),
        );

        $keys = array_map(static fn($tag): string => $tag->key, $head->build('About')->tags);

        static::assertSame(
            [true, true, true, true],
            [
                in_array('og:image', $keys, strict: true),
                in_array('og:description', $keys, strict: true),
                str_contains($head->render('About'), 'content="Default"'),
                str_contains($head->render('About'), 'share.jpg'),
            ],
        );
    }

    #[Test]
    public function theHomePageGetsTheSitesJsonLdAndAnotherPageItsTrail(): void
    {
        static::assertSame([['Organization', 'WebSite'], ['BreadcrumbList']], [
            array_column(self::head('https://www.example.test/')->build('Home')->jsonLd, '@type'),
            array_column(self::head('https://www.example.test/about')->build('About')->jsonLd, '@type'),
        ]);
    }

    #[Test]
    public function theHomePageIsAtTheRootByDefault(): void
    {
        $current = new CurrentRequest();
        $current->set(new ServerRequest(uri: 'https://www.example.test/'));

        static::assertNotSame([], (new SeoHead(SeoSettings::config(), $current))->build('Home')->jsonLd);
    }

    #[Test]
    public function theHomePathsCanBeConfigured(): void
    {
        static::assertSame(
            ['Organization', 'WebSite'],
            array_column(self::head('https://www.example.test/en', homePaths: ['/en'])->build('Home')->jsonLd, '@type'),
        );
    }

    #[Test]
    public function theJsonLdHelperGivesTheGraph(): void
    {
        $graph = new Graph();

        static::assertSame($graph, (new JsonLdHelper($graph))());
    }

    #[Test]
    public function theMarkupHoldsTheTitleAndTagsAndTheGraphTheJsonLd(): void
    {
        $graph = new Graph();
        $graph->append(['@type' => 'CreativeWork']);
        $current = new CurrentRequest();
        $current->set(new ServerRequest(uri: 'https://www.example.test/'));
        $head = new SeoHead(SeoSettings::config(), $current, graph: $graph);

        $html = $head->render('Home');

        static::assertSame(
            [true, false, $graph, ['CreativeWork', 'Organization', 'WebSite']],
            [
                str_contains($html, '<title>Home | Example</title>'),
                str_contains($html, 'application/ld+json'),
                $head->graph(),
                array_column($graph->nodes(), '@type'),
            ],
        );
    }

    #[Test]
    public function thePageMainNodeCarriesItsMetadata(): void
    {
        $published = new DateTimeImmutable('2026-10-01');
        $updated   = new DateTimeImmutable('2026-10-02');

        static::assertEquals(
            new Article('https://www.example.test/a', 'A', 'About A.', '/a.jpg', $published, $updated, 'WebPage'),
            SeoHead::mainNode(new PageMetadata(
                'https://www.example.test/a',
                title: 'A',
                description: 'About A.',
                image: '/a.jpg',
                updated: $updated,
                published: $published,
                schema: 'WebPage',
            )),
        );
    }

    #[Test]
    public function thePagesOwnDescriptionAndImageAreLeftAlone(): void
    {
        $head = self::head(
            'https://www.example.test/about',
            SeoSettings::config([
                'extensions' => ['seo' => ['description' => 'Default', 'share_image' => '/share.jpg']],
            ]),
        );

        static::assertSame(
            [true, false],
            [
                in_array('og:description', self::keys($head), strict: true),
                in_array('og:description', self::keys($head, hasDescription: true, hasImage: true), strict: true),
            ],
        );
    }

    /**
     * @param list<string> $names
     */
    #[Test]
    #[DataProvider('trailNamesProvider')]
    public function theTrailIsNamedFromTheSitesNavigation(RouterInterface $router, array $names): void
    {
        $current = new CurrentRequest();
        $current->set(new ServerRequest(uri: 'https://www.example.test/work/convoy'));
        $head = new SeoHead(SeoSettings::config(), $current, navigation: new WorkflowNavigationPages(
            new FixedStrategy([['route' => 'work', 'label' => 'Our work']], ['work' => []]),
            $router,
        ));

        static::assertSame($names, array_column($head->build('Convoy')->jsonLd[0]['itemListElement'] ?? [], 'name'));
    }

    #[Test]
    public function theViewHelperGivesTheHead(): void
    {
        $head = self::head('https://www.example.test/');

        static::assertSame($head, (new SeoHelper($head))());
    }

    #[Test]
    public function withoutARequestThePageIsNotTheHomePage(): void
    {
        $head = new SeoHead(SeoSettings::config(), new CurrentRequest());

        static::assertSame(['Home | Example', []], [$head->build('Home')->title, $head->build('Home')->jsonLd]);
    }
}
