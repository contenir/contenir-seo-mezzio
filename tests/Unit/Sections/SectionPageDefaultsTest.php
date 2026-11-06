<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Unit\Sections;

use Contenir\Metadata\MetadataInterface;
use Contenir\Resource\Core\Metadata\PageDefaults;
use Contenir\Resource\Core\Metadata\PageOverrides;
use Contenir\Seo\Mezzio\Sections\SectionPageDefaults;
use Contenir\Seo\Mezzio\Tests\TestAsset\LinkedProject;
use Contenir\Seo\Mezzio\Tests\TestAsset\ProjectEntity;
use Contenir\Seo\Sections\SectionMap;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

use function get_object_vars;
use function implode;
use function sprintf;

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

    /**
     * @return array<string, array{string}>
     */
    public static function missingRelations(): array
    {
        return [
            'one the entity does not have' => ['nothing.path'],
            'one not yet set'              => ['unset.path'],
            'one that is empty'            => ['hero.path'],
        ];
    }

    private static function asset(string $type, string $path): stdClass
    {
        $asset       = new stdClass();
        $asset->type = $type;
        $asset->path = $path;

        return $asset;
    }

    /**
     * @param list<string> $sources
     */
    private static function linkedImage(LinkedProject $resource, array $sources): ?string
    {
        $resource->resourceTypeId = 'project';
        $xml                      = sprintf(
            '<admin><library><category><projects><section><article><extensions>'
                . '<seo type="project"><image>%s</image></seo>'
                . '</extensions></article></section></projects></category></library></admin>',
            implode(', ', $sources),
        );

        return (new SectionPageDefaults(
            SectionMap::fromAdminXml($xml),
            get_object_vars(...),
        ))->for($resource)?->image;
    }

    private static function resource(string $type): ProjectEntity
    {
        $resource                 = new ProjectEntity();
        $resource->resourceTypeId = $type;

        return $resource;
    }

    #[Test]
    public function aLinkWithNoMatchFallsThroughToTheNextSource(): void
    {
        $resource         = new LinkedProject();
        $resource->assets = [self::asset('thumbnail', '/thumb.jpg')];

        static::assertSame(
            '/thumb.jpg',
            self::linkedImage($resource, ['assets[type=hero].path', 'assets[type=thumbnail].path']),
        );
    }

    #[Test]
    #[DataProvider('missingRelations')]
    public function aLinkWithNothingToFollowGivesNoImage(string $source): void
    {
        static::assertNull(self::linkedImage(new LinkedProject(), [$source]));
    }

    #[Test]
    public function aLinkWithoutAFilterTakesAnyLinkedRecord(): void
    {
        $resource         = new LinkedProject();
        $resource->assets = [self::asset('hero', '/hero.jpg')];

        static::assertSame('/hero.jpg', self::linkedImage($resource, ['assets[type=none].path', 'assets.path']));
    }

    #[Test]
    public function anImageCanComeFromTheFirstLinkedRecordOfAKind(): void
    {
        $resource         = new LinkedProject();
        $resource->assets = [self::asset('gallery', '/gallery.jpg'), self::asset('hero', '/hero.jpg')];

        static::assertSame('/hero.jpg', self::linkedImage($resource, ['assets[type=hero].path']));
    }

    #[Test]
    public function anImageCanComeFromTheOneRecordARelationHolds(): void
    {
        $resource       = new LinkedProject();
        $resource->hero = self::asset('hero', '/hero.jpg');

        static::assertSame('/hero.jpg', self::linkedImage($resource, ['hero.path']));
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

    #[Test]
    public function whatARelationHoldsThatIsNoRecordIsPassedOver(): void
    {
        $resource         = new LinkedProject();
        $resource->assets = ['/not-a-record.jpg', self::asset('hero', '/hero.jpg')];

        static::assertSame('/hero.jpg', self::linkedImage($resource, ['assets.path']));
    }

    #[Test]
    public function withTheRecordsSwitchOffARecordWithNoSeoColumnKeepsItsOwnTitleDescriptionAndImage(): void
    {
        $defaults = new SectionPageDefaults(
            SectionMap::fromAdminXml(self::ADMIN),
            static fn(): array => ['title' => 'House', 'abstract' => 'A house.', 'image_lg' => '/house.jpg'],
            records: false,
        );

        static::assertEquals(
            new PageDefaults('House', 'A house.', '/house.jpg'),
            $defaults->for(self::resource('project')),
        );
    }

    #[Test]
    public function withTheRecordsSwitchOffOnlyTheTitleDescriptionAndPlainImageAreGiven(): void
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
            records: false,
        );

        static::assertEquals(
            new PageDefaults('House', 'A house.', '/house.jpg', overrides: new PageOverrides('Share', 'Shared.')),
            $defaults->for(self::resource('project')),
        );
    }
}
