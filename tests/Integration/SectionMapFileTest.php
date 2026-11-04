<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Integration;

use Contenir\Seo\Mezzio\Sections\SectionMapFile;
use Contenir\Seo\Sections\SectionMap;
use Contenir\Seo\Sections\SeoBlock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function file_put_contents;
use function filemtime;
use function filesize;
use function glob;
use function is_dir;
use function is_file;
use function json_encode;
use function mkdir;
use function rmdir;
use function sprintf;
use function str_replace;
use function sys_get_temp_dir;
use function touch;
use function uniqid;
use function unlink;

#[Group('integration')]
final class SectionMapFileTest extends TestCase
{
    private const string ADMIN =
        '<admin><library><category><projects><section><article><extensions>'
            . '<seo type="project"/></extensions></article></section></projects></category></library></admin>';

    private const string STAMP = '{stamp}';

    private string $dir = '';

    /**
     * @return array<string, array{mixed}>
     */
    public static function brokenCacheProvider(): array
    {
        return [
            'no stamp'          => [['blocks' => []]],
            'no blocks'         => [['stamp' => self::STAMP]],
            'blocks not a map'  => [['stamp' => self::STAMP, 'blocks' => 'project']],
            'a numbered block'  => [['stamp' => self::STAMP, 'blocks' => [['schema' => 'WebPage']]]],
            'a block not a map' => [['stamp' => self::STAMP, 'blocks' => ['project' => 'WebPage']]],
            'not an object'     => ['project'],
        ];
    }

    /**
     * @return list<string>
     */
    private static function files(string $pattern): array
    {
        $found = glob($pattern);

        return false === $found ? [] : $found;
    }

    private static function remove(string $path): void
    {
        foreach (self::files("{$path}/*") as $entry) {
            is_dir($entry) ? self::remove($entry) : unlink($entry);
        }

        rmdir($path);
    }

    private static function stamp(string $file): string
    {
        return sprintf('%d:%d', (int) filemtime($file), (int) filesize($file));
    }

    #[Test]
    #[DataProvider('brokenCacheProvider')]
    public function aCacheOfAnotherShapeIsRebuilt(mixed $cache): void
    {
        $admin = $this->admin();
        file_put_contents($this->cache(), str_replace(self::STAMP, self::stamp($admin), (string) json_encode($cache)));

        static::assertSame(['project'], array_keys((new SectionMapFile($admin, $this->cache()))->load()->blocks));
    }

    #[Test]
    public function aCacheThatIsNotJsonIsRebuilt(): void
    {
        $admin = $this->admin();
        file_put_contents($this->cache(), data: 'not json');

        static::assertSame(['project'], array_keys((new SectionMapFile($admin, $this->cache()))->load()->blocks));
    }

    #[Test]
    public function aChangedFileIsReadAgain(): void
    {
        $admin = $this->admin();
        $this->keep(self::stamp($admin), ['news' => []]);
        touch($admin, (int) filemtime($admin) + 60);

        static::assertSame(['project'], array_keys((new SectionMapFile($admin, $this->cache()))->load()->blocks));
    }

    #[Test]
    public function aKeptBlockMissingItsPartsTakesNone(): void
    {
        $admin = $this->admin();
        $this->keep(self::stamp($admin), [
            'news' => ['schema' => 3, 'roles' => 'x', 'required' => 'y', 'advanced' => []],
        ]);

        static::assertEquals(
            new SectionMap(['news' => new SeoBlock('news', null, [], [], null)]),
            (new SectionMapFile($admin, $this->cache()))->load(),
        );
    }

    #[Test]
    public function aKeptMapForTheSameFileIsServedWithoutReadingIt(): void
    {
        $admin = $this->admin();
        $this->keep(self::stamp($admin), [
            'news' => [
                'schema'   => 'NewsArticle',
                'roles'    => ['title' => ['headline']],
                'required' => ['title'],
                'advanced' => 'seo',
            ],
        ]);

        static::assertEquals(
            new SectionMap(['news' => new SeoBlock(
                'news',
                'NewsArticle',
                ['title' => ['headline']],
                ['title'],
                'seo',
            )]),
            (new SectionMapFile($admin, $this->cache()))->load(),
        );
    }

    #[Test]
    public function aMissingCacheFolderKeepsNothing(): void
    {
        $map = (new SectionMapFile($this->admin(), "{$this->dir}/missing/map.cache"))->load();

        static::assertSame([['project'], false], [array_keys($map->blocks), is_dir("{$this->dir}/missing")]);
    }

    #[Test]
    public function aSiteWithoutTheAdminXmlMapsNothing(): void
    {
        static::assertEquals(new SectionMap(), (new SectionMapFile("{$this->dir}/none.xml", $this->cache()))->load());
    }

    #[Test]
    public function readsTheAdminXmlAndKeepsTheMap(): void
    {
        $map = (new SectionMapFile($this->admin(), $this->cache()))->load();

        static::assertSame([['project'], true], [array_keys($map->blocks), is_file($this->cache())]);
    }

    #[Test]
    public function theCacheIsWrittenIntoAFolderThatExists(): void
    {
        (new SectionMapFile($this->admin(), $this->cache()))->load();

        static::assertTrue(is_file($this->cache()));
    }

    #[Test]
    public function theKeptMapReadsBackAsItWasMade(): void
    {
        $admin = $this->admin();
        $made  = (new SectionMapFile($admin, $this->cache()))->load();

        static::assertEquals($made->blocks, (new SectionMapFile($admin, $this->cache()))->load()->blocks);
    }

    #[Test]
    public function withoutACacheFileTheAdminXmlIsReadAndNothingIsWritten(): void
    {
        $map = (new SectionMapFile($this->admin()))->load();

        static::assertSame([['project'], ["{$this->dir}/admin.xml"]], [
            array_keys($map->blocks),
            self::files("{$this->dir}/*"),
        ]);
    }

    protected function setUp(): void
    {
        $this->dir = sprintf('%s/seo-map-%s', sys_get_temp_dir(), uniqid());
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        self::remove($this->dir);
    }

    private function admin(): string
    {
        file_put_contents("{$this->dir}/admin.xml", self::ADMIN);

        return "{$this->dir}/admin.xml";
    }

    private function cache(): string
    {
        return "{$this->dir}/map.cache";
    }

    /**
     * @param array<string, array<string, mixed>> $blocks
     */
    private function keep(string $stamp, array $blocks): void
    {
        file_put_contents($this->cache(), (string) json_encode(['stamp' => $stamp, 'blocks' => $blocks]));
    }
}
