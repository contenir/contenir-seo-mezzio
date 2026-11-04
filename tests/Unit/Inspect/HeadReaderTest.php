<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Unit\Inspect;

use Contenir\Seo\Mezzio\Inspect\HeadReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function libxml_get_errors;
use function libxml_use_internal_errors;

#[Group('unit')]
final class HeadReaderTest extends TestCase
{
    private const string PAGE = <<<'HTML'
        <!DOCTYPE html>
        <html><head>
            <title> House | Studio </title>
            <meta name="description" content="A house by the sea.">
            <meta property="og:image" content="https://site.test/house.jpg">
            <meta property="og:image:alt" content="The house from the garden">
            <link rel="canonical" href="https://site.test/work/house">
            <meta name="robots" content="noindex, follow">
            <script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"CreativeWork","name":"House"},"not a node",{"@type":"BreadcrumbList"}]}</script>
            <script type="application/ld+json">{"@type":"Organization","name":"Studio"}</script>
            <script type="application/ld+json">not json</script>
            <script type="application/ld+json">{"@graph":"not a list"}</script>
            <script type="application/ld+json">"just text"</script>
        </head><body><p>House</p></body></html>
        HTML;

    /**
     * @return array<string, array{bool}>
     */
    public static function errorSettingProvider(): array
    {
        return ['errors kept internally' => [true], 'errors reported' => [false]];
    }

    #[Test]
    public function anEmptyPageHasNothing(): void
    {
        static::assertSame([null, []], [HeadReader::read('  ')['title'], HeadReader::read('')['jsonLd']]);
    }

    #[Test]
    public function aPageWithoutThemHasNone(): void
    {
        static::assertSame(
            [
                'title'       => null,
                'description' => null,
                'image'       => null,
                'imageAlt'    => null,
                'canonical'   => null,
                'robots'      => null,
                'jsonLd'      => [],
            ],
            HeadReader::read('<html><head><title> </title></head><body></body></html>'),
        );
    }

    #[Test]
    #[DataProvider('errorSettingProvider')]
    public function readingLeavesNoErrorsBehindAndRestoresTheSetting(bool $setting): void
    {
        $previous = libxml_use_internal_errors($setting);
        HeadReader::read('<html><head><title>Old</title></head><body><blink>tag libxml rejects</blink></body></html>');

        static::assertSame([[], $setting], [libxml_get_errors(), libxml_use_internal_errors($previous)]);
    }

    #[Test]
    public function readsWhatThePageTellsSearchEnginesAndSharingSites(): void
    {
        static::assertSame(
            [
                'title'       => 'House | Studio',
                'description' => 'A house by the sea.',
                'image'       => 'https://site.test/house.jpg',
                'imageAlt'    => 'The house from the garden',
                'canonical'   => 'https://site.test/work/house',
                'robots'      => 'noindex, follow',
                'jsonLd'      => [
                    ['@type' => 'CreativeWork', 'name' => 'House'],
                    ['@type' => 'BreadcrumbList'],
                    ['@type' => 'Organization', 'name' => 'Studio'],
                ],
            ],
            HeadReader::read(self::PAGE),
        );
    }
}
