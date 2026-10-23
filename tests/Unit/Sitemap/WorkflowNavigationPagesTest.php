<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Unit\Sitemap;

use Contenir\Seo\Mezzio\Sitemap\WorkflowNavigationPages;
use Contenir\Seo\Mezzio\Tests\TestAsset\FixedStrategy;
use Contenir\Seo\Mezzio\Tests\TestAsset\RouteNameRouter;
use Contenir\Seo\Sitemap\Page;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

use const DATE_ATOM;

#[Group('unit')]
final class WorkflowNavigationPagesTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $navigation
     *
     * @return list<Page>
     */
    private static function pages(array $navigation, array $routes): array
    {
        return (new WorkflowNavigationPages(new FixedStrategy($navigation, $routes), new RouteNameRouter()))->pages();
    }

    #[Test]
    public function aPagesLastmodIsWhenItChanged(): void
    {
        $changed = new DateTimeImmutable('2024-01-30T14:09:33+00:00');
        $pages   = self::pages(
            [
                ['route' => 'a', 'lastmod' => '2024-01-30T14:09:33+00:00'],
                ['route' => 'b', 'lastmod' => $changed],
                ['route' => 'c', 'lastmod' => 'not a date'],
                ['route' => 'd', 'lastmod' => ''],
                ['route' => 'e'],
            ],
            ['a' => [], 'b' => [], 'c' => [], 'd' => [], 'e' => []],
        );

        static::assertSame(
            ['2024-01-30T14:09:33+00:00', '2024-01-30T14:09:33+00:00', null, null, null],
            array_map(static fn(Page $page): ?string => $page->modified?->format(DATE_ATOM), $pages),
        );
    }

    #[Test]
    public function everyRoutedPageIsListedHiddenFromTheMenuOrNot(): void
    {
        $pages = self::pages(
            [
                'not a page',
                ['route' => 'page-1', 'visible' => false],
                ['route' => 'page-2', 'pages' => ['not a page', ['route' => 'page-3']]],
                ['route' => 'folder', 'pages' => [['route' => 'page-4']]],
                ['label' => 'no route'],
            ],
            ['page-1' => [], 'page-2' => [], 'page-3' => [], 'page-4' => []],
        );

        static::assertSame(
            ['/page-1', '/page-2', '/page-3', '/page-4'],
            array_map(static fn(Page $page): string => $page->url, $pages),
        );
    }
}
