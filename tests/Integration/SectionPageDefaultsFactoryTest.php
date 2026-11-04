<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Integration;

use Contenir\Db\Model\EntityManager;
use Contenir\Resource\Core\Metadata\PageDefaultsInterface;
use Contenir\Seo\Mezzio\ConfigProvider;
use Contenir\Seo\Mezzio\Factory\SectionPageDefaultsFactory;
use Contenir\Seo\Mezzio\Tests\TestAsset\ArrayContainer;
use Contenir\Seo\Mezzio\Tests\TestAsset\ProjectEntity;
use PhpDb\Adapter\AdapterInterface;
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
    public function aSiteWithoutTheFileMapsNothing(): void
    {
        $resource                 = new ProjectEntity();
        $resource->resourceTypeId = 'project';

        static::assertNull(
            (new SectionPageDefaultsFactory())($this->container("{$this->file}.missing"))->for($resource),
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

    private function container(string $adminXml, string|false $cache = false): ArrayContainer
    {
        return new ArrayContainer([
            'config'             => ['contenir_seo' => ['admin_xml' => $adminXml, 'section_map_cache' => $cache]],
            EntityManager::class => new EntityManager($this->createStub(AdapterInterface::class)),
        ]);
    }
}
