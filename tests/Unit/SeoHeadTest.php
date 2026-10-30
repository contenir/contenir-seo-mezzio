<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Unit;

use Contenir\Seo\Head\Article;
use Contenir\Seo\JsonLd\Graph;
use Contenir\Seo\Mezzio\CurrentRequest;
use Contenir\Seo\Mezzio\SeoHead;
use Contenir\Seo\Mezzio\Tests\TestAsset\SeoSettings;
use Contenir\Seo\Mezzio\View\JsonLdHelper;
use Contenir\Seo\Mezzio\View\SeoHelper;
use Contenir\Seo\SeoConfig;
use Laminas\Diactoros\ServerRequest;
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
    public function aPageTitleFollowsTheSitesTemplate(): void
    {
        static::assertSame('About | Example', self::head('https://www.example.test/about')->build('About')->title);
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
