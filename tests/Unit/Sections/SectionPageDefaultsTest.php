<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Unit\Sections;

use Contenir\Metadata\MetadataInterface;
use Contenir\Resource\Core\Metadata\PageDefaults;
use Contenir\Resource\Core\Metadata\PageOverrides;
use Contenir\Seo\Mezzio\Sections\SectionPageDefaults;
use Contenir\Seo\Mezzio\Tests\TestAsset\ProjectEntity;
use Contenir\Seo\Sections\SectionMap;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SectionPageDefaultsTest extends TestCase
{
    private const string ADMIN = <<<'XML'
        <admin><library><category><projects><section><article><extensions>
            <seo type="project" schema="CreativeWork">
                <description>abstract</description>
                <image alt="caption">image_lg</image>
                <advanced>seo</advanced>
            </seo>
        </extensions></article></section></projects></category></library></admin>
        XML;

    private static function resource(string $type): ProjectEntity
    {
        $resource                 = new ProjectEntity();
        $resource->resourceTypeId = $type;

        return $resource;
    }

    #[Test]
    public function aResourceTakesWhatItsTypesBlockMakesOfItsColumns(): void
    {
        $defaults = new SectionPageDefaults(
            SectionMap::fromAdminXml(self::ADMIN),
            static fn(): array => [
                'title'    => 'House',
                'abstract' => 'A house.',
                'image_lg' => '/house.jpg',
                'caption'  => 'The house from the garden',
                'seo'      => '{"canonical": "/work/house", "robots": "noindex, follow", "title": "Share",'
                    . ' "description": "Shared.", "image": "/share.jpg", "image_alt": "Share alt"}',
            ],
        );

        static::assertEquals(
            new PageDefaults(
                'House',
                'A house.',
                '/house.jpg',
                'The house from the garden',
                '/work/house',
                'noindex, follow',
                'CreativeWork',
                new PageOverrides('Share', 'Shared.', '/share.jpg', 'Share alt'),
            ),
            $defaults->for(self::resource('project')),
        );
    }

    #[Test]
    public function aResourceWhoseTypeNoBlockMapsHasNoneAndIsNotRead(): void
    {
        $defaults = new SectionPageDefaults(
            SectionMap::fromAdminXml(self::ADMIN),
            static fn(): array => throw new LogicException('An unmapped resource was read'),
        );

        static::assertNull($defaults->for(self::resource('news')));
    }

    #[Test]
    public function metadataThatIsNotAResourceHasNone(): void
    {
        $defaults = new SectionPageDefaults(
            SectionMap::fromAdminXml(self::ADMIN),
            static fn(): array => throw new LogicException('Metadata was read as a resource'),
        );

        static::assertNull($defaults->for($this->createStub(MetadataInterface::class)));
    }
}
