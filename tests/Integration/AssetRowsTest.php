<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Integration;

use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\RowValues;
use Contenir\Seo\Mezzio\Sections\AssetRows;
use Contenir\Seo\Mezzio\Sections\ContentImage;
use Contenir\Seo\Mezzio\Tests\TestAsset\AssetEntity;
use PDO;
use PhpDb\Adapter\Adapter;
use PhpDb\Sqlite\AdapterPlatform;
use PhpDb\Sqlite\Pdo\Connection;
use PhpDb\Sqlite\Pdo\Driver;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[Group('integration')]
final class AssetRowsTest extends TestCase
{
    private AssetRows $rows;

    #[Test]
    public function aContentImageTakesTheFirstActiveImageInTheOrderTheBlockNamesThem(): void
    {
        $image = new ContentImage($this->rows->of(...));
        $row   = ['section' => json_encode([[
            'type'   => 'media',
            'status' => '1',
            'fields' => ['images' => ['2', '4', '3', '1']],
        ]])];

        static::assertSame('/asset/three.jpg', $image->pathOf($row));
    }

    #[Test]
    public function findsNothingForNoMatches(): void
    {
        static::assertSame([], $this->rows->of(['404']));
    }

    #[Test]
    public function findsTheAssetsAJsonSectionNamesByTheirIds(): void
    {
        static::assertSame(
            ['1' => 'image', '3' => 'image'],
            $this->types($this->rows->of(['1', '3', '99'])),
        );
    }

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE asset (asset_id INTEGER PRIMARY KEY, type TEXT, path TEXT, active TEXT)');
        $pdo->exec(<<<'SQL'
            INSERT INTO asset (asset_id, type, path, active) VALUES
                (1, 'image', '/asset/one.jpg', 'active'),
                (2, 'video', '/asset/two.mp4', 'active'),
                (3, 'image', '/asset/three.jpg', NULL),
                (4, 'image', '/asset/four.jpg', 'archived')
            SQL);
        $driver     = new Driver(connection: new Connection($pdo));
        $em         = new EntityManager(new Adapter($driver, new AdapterPlatform($driver)));
        $this->rows = new AssetRows($em, new RowValues($em), AssetEntity::class);
    }

    /**
     * @param array<string, array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    private function types(array $rows): array
    {
        $types = [];
        foreach ($rows as $id => $row) {
            $types[$id] = $row['type'];
        }

        return $types;
    }
}
