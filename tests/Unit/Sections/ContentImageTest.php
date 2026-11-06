<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Unit\Sections;

use Contenir\Seo\Mezzio\Sections\ContentImage;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[Group('unit')]
final class ContentImageTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function rowsWithoutImages(): array
    {
        return [
            'no column'         => [null],
            'no blocks'         => ['[]'],
            'only text'         => [(string) json_encode([['type' => 'text', 'fields' => ['description' => 'x']]])],
            'a media block off' => [(string) json_encode([[
                'type'   => 'media',
                'status' => '',
                'fields' => ['images' => ['1']],
            ]])],
        ];
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, string>
     */
    private static function row(array $ids): array
    {
        return ['section' => (string) json_encode([[
            'type'   => 'media',
            'status' => '1',
            'fields' => ['images' => $ids],
        ]])];
    }

    #[Test]
    public function anAssetWithNoTypeOrStatusCountsAsAnActiveImage(): void
    {
        $image = new ContentImage(static fn(): array => ['5' => ['path' => '/e.jpg']]);

        static::assertSame('/e.jpg', $image->pathOf(self::row(['5'])));
    }

    #[Test]
    public function anIdWithNoAssetIsSkipped(): void
    {
        $image = new ContentImage(static fn(): array => ['2' => ['path' => '/b.jpg']]);

        static::assertSame('/b.jpg', $image->pathOf(self::row(['1', '2'])));
    }

    #[Test]
    public function aPathThatIsNoTextIsNoImage(): void
    {
        $image = new ContentImage(static fn(): array => ['5' => ['path' => ['/e.jpg']], '6' => ['path' => null]]);

        static::assertNull($image->pathOf(self::row(['5', '6'])));
    }

    #[Test]
    public function asksForOnlyTheIdsTheSectionNames(): void
    {
        $asked = [];
        $image = new ContentImage(static function (array $ids) use (&$asked): array {
            $asked = $ids;

            return [];
        });
        $image->pathOf(self::row(['7', '8']));

        static::assertSame(['7', '8'], $asked);
    }

    #[Test]
    #[DataProvider('rowsWithoutImages')]
    public function looksNothingUpWhereTheSectionNamesNoImage(mixed $section): void
    {
        $image = new ContentImage(static fn(): never => throw new LogicException('Assets were looked up'));

        static::assertNull($image->pathOf(['section' => $section]));
    }

    #[Test]
    public function takesTheFirstImageThatCanBeShown(): void
    {
        $image = new ContentImage(static fn(array $ids): array => [
            '1' => ['type' => 'video', 'path' => '/a.mp4'],
            '2' => ['type' => 'image', 'path' => '/b.jpg', 'active' => 'archived'],
            '3' => ['type' => 'image', 'path' => '  '],
            '4' => ['type' => 'image', 'path' => '/d.jpg', 'active' => 'active'],
            '5' => ['path' => '/e.jpg'],
        ]);

        static::assertSame('/d.jpg', $image->pathOf(self::row(['1', '2', '3', '4', '5'])));
    }
}
