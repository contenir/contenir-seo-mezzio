<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Integration;

use Contenir\Db\Model\EntityManager;
use Contenir\Resource\Core\Metadata\PageDefaultsInterface;
use Contenir\Seo\Mezzio\ConfigProvider;
use Contenir\Seo\Mezzio\Factory\SectionPageDefaultsFactory;
use Contenir\Seo\Mezzio\Sections\SectionPageDefaults;
use Contenir\Seo\Mezzio\Tests\TestAsset\ArrayContainer;
use Contenir\Seo\Mezzio\Tests\TestAsset\AssetEntity;
use Contenir\Seo\Mezzio\Tests\TestAsset\ProjectEntity;
use Contenir\Seo\Mezzio\Tests\TestAsset\SeoSettings;
use Contenir\Seo\SeoConfig;
use PDO;
use PhpDb\Adapter\Adapter;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Sqlite\AdapterPlatform;
use PhpDb\Sqlite\Pdo\Connection;
use PhpDb\Sqlite\Pdo\Driver;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function glob;
use function is_file;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[Group('integration')]
final class SectionPageDefaultsFactoryTest extends TestCase
{
    private string $file = '';

    #[Test]
    public function aCacheSettingOfFalseKeepsNothing(): void
    {
        file_put_contents($this->file, data: '<admin/>');
        (new SectionPageDefaultsFactory())($this->container($this->file));

        static::assertSame([false, []], [is_file("{$this->file}.cache"), glob("{$this->file}*.tmp")]);
    }

    #[Test]
    public function anAssetEntityLetsARecordsContentImageStandInForAShareImage(): void
    {
        file_put_contents($this->file, <<<'XML'
            <admin><library><category><projects><section><article><extensions>
                <seo type="project"><image alt="caption">image_lg</image><advanced>seo</advanced></seo>
            </extensions></article></section></projects></category></library></admin>
            XML);
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE asset (asset_id INTEGER PRIMARY KEY, type TEXT, path TEXT, active TEXT)');
        $pdo->exec(
            "INSERT INTO asset (asset_id, type, path, active) VALUES (7, 'image', '/asset/seven.jpg', 'active')",
        );
        $driver                   = new Driver(connection: new Connection($pdo));
        $resource                 = new ProjectEntity();
        $resource->resourceTypeId = 'project';
        $resource->section        = '[{"type":"media","status":"1","fields":{"images":["7"]}}]';

        $defaults = (new SectionPageDefaultsFactory())($this->container(
            $this->file,
            assetEntity: AssetEntity::class,
            adapter: new Adapter($driver, new AdapterPlatform($driver)),
        ))->for($resource);

        static::assertSame('/asset/seven.jpg', $defaults?->image);
    }

    #[Test]
    public function anAssetEntityThatIsNotAClassIsLeftOut(): void
    {
        file_put_contents($this->file, data: '<admin/>');

        static::assertInstanceOf(
            SectionPageDefaults::class,
            (new SectionPageDefaultsFactory())($this->container(
                $this->file,
                assetEntity: SectionPageDefaults::class . 'Missing',
            )),
        );
    }

    #[Test]
    public function aSiteWithoutTheFileMapsNothing(): void
    {
        $resource                 = new ProjectEntity();
        $resource->resourceTypeId = 'project';

        static::assertNull(
            (new SectionPageDefaultsFactory())($this->container("{$this->file}.missing"))->for($resource),
        );
    }

    #[Test]
    public function givesOnlyATitleAndDescriptionWhileTheRecordsSwitchIsOff(): void
    {
        file_put_contents($this->file, <<<'XML'
            <admin><library><category><projects><section><article><extensions>
                <seo type="project" schema="CreativeWork"><advanced>seo</advanced></seo>
            </extensions></article></section></projects></category></library></admin>
            XML);
        $resource                 = new ProjectEntity();
        $resource->resourceTypeId = 'project';
        $resource->seo            = '{"title": "Own title", "description": "Own description", "robots": "noindex"}';

        $defaults = (new SectionPageDefaultsFactory())($this->container($this->file, settings: [
            'extensions' => ['seo' => ['records' => '0']],
        ]))->for($resource);

        static::assertSame(
            ['Own title', 'Own description', null, null],
            [$defaults?->overrides->title, $defaults?->overrides->description, $defaults?->robots, $defaults?->schema],
        );
    }

    #[Test]
    public function keepsTheMapInTheConfiguredCacheFile(): void
    {
        file_put_contents($this->file, data: '<admin/>');
        $cache = "{$this->file}.cache";
        (new SectionPageDefaultsFactory())($this->container($this->file, $cache));
        $kept = is_file($cache);
        unlink($cache);

        static::assertTrue($kept);
    }

    #[Test]
    public function readsTheBlocksInTheConfiguredAdminXmlAndTheResourcesOwnColumns(): void
    {
        file_put_contents($this->file, <<<'XML'
            <admin><library><category><projects><section><article><extensions>
                <seo type="project" schema="CreativeWork"><advanced>seo</advanced></seo>
            </extensions></article></section></projects></category></library></admin>
            XML);
        $resource                 = new ProjectEntity();
        $resource->resourceTypeId = 'project';
        $resource->metaTitle      = 'House';
        $resource->seo            = '{"robots": "noindex"}';

        $defaults = (new SectionPageDefaultsFactory())($this->container($this->file))->for($resource);

        static::assertSame(
            ['House', 'noindex', 'CreativeWork'],
            [$defaults?->title, $defaults?->robots, $defaults?->schema],
        );
    }

    #[Test]
    public function theConfigProviderOffersTheDefaultsToThePageMetadataBuilder(): void
    {
        static::assertSame(
            SectionPageDefaultsFactory::class,
            (new ConfigProvider())()['dependencies']['factories'][PageDefaultsInterface::class] ?? null,
        );
    }

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), prefix: 'admin');
    }

    protected function tearDown(): void
    {
        unlink($this->file);
    }

    /**
     * @param array<array-key, mixed> $settings
     */
    private function container(
        string $adminXml,
        string|false $cache = false,
        array $settings = [],
        string $assetEntity = '',
        ?AdapterInterface $adapter = null,
    ): ArrayContainer {
        return new ArrayContainer([
            'config'             => ['contenir_seo' => [
                'admin_xml'         => $adminXml,
                'section_map_cache' => $cache,
                'asset_entity'      => $assetEntity,
            ]],
            SeoConfig::class     => SeoSettings::config($settings),
            EntityManager::class => new EntityManager($adapter ?? $this->createStub(AdapterInterface::class)),
        ]);
    }
}
