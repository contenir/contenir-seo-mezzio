<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Unit\Sitemap;

use Contenir\Resource\Core\Entity\AbstractResourceEntity;
use Contenir\Resource\Core\Metadata\PageMetadataBuilder;
use Contenir\Resource\Core\ResourceManagerInterface;
use Contenir\Seo\Mezzio\Sitemap\PageImages;
use Contenir\Seo\Mezzio\Tests\TestAsset\ImagedProject;
use Contenir\Seo\Sitemap\Page;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PageImagesTest extends TestCase
{
    /**
     * @return array<string, array{int|string}>
     */
    public static function idProvider(): array
    {
        return ['a number' => [4], 'text' => ['4']];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function noRecordProvider(): array
    {
        return [
            'no route'              => [null],
            'no options'            => [['path' => '/work/house']],
            'options not a map'     => [['options' => 'defaults']],
            'no defaults'           => [['options' => []]],
            'defaults not a map'    => [['options' => ['defaults' => 'id']]],
            'no record keys'        => [['options' => ['defaults' => []]]],
            'record keys not a map' => [['options' => ['defaults' => ['id' => 4]]]],
            'no record id'          => [['options' => ['defaults' => ['id' => []]]]],
            'an id of another kind' => [['options' => ['defaults' => ['id' => ['resourceId' => 4.5]]]]],
        ];
    }

    private static function project(?string $image): ImagedProject
    {
        $project        = new ImagedProject();
        $project->image = $image;

        return $project;
    }

    private static function route(int|string $id): array
    {
        return ['options' => ['defaults' => ['id' => ['resourceId' => $id]]]];
    }

    #[Test]
    #[DataProvider('idProvider')]
    public function aPageListsItsRecordsShareImageAtTheSitesAddress(int|string $id): void
    {
        $modified = new DateTimeImmutable('2026-11-05T09:00:00+00:00');
        $page     = new Page('/work/house', $modified, robots: 'noindex', workflow: 'project');

        static::assertEquals(
            new Page('/work/house', $modified, ['https://www.example.test/asset/house.jpg'], 'noindex', 'project'),
            $this->images(self::project('/asset/house.jpg'), $id)->add($page, self::route($id)),
        );
    }

    #[Test]
    public function aPageWhoseRecordHasNoImageListsNone(): void
    {
        $page = new Page('/work/house');

        static::assertSame($page, $this->images(self::project(null))->add($page, self::route(4)));
    }

    #[Test]
    public function aPageWhoseRecordIsNotPublishedListsNoImage(): void
    {
        $page = new Page('/work/house');

        static::assertSame($page, $this->images(null)->add($page, self::route(4)));
    }

    #[Test]
    #[DataProvider('noRecordProvider')]
    public function aPageWithoutARecordListsNoImage(mixed $route): void
    {
        $page = new Page('/work/house');

        static::assertSame($page, $this->images(self::project('/asset/house.jpg'))->add($page, $route));
    }

    private function images(?AbstractResourceEntity $record, int|string $id = 4): PageImages
    {
        $resources = $this->createStub(ResourceManagerInterface::class);
        $resources->method('findActive')
            ->willReturnCallback(static fn(int|string $asked): ?AbstractResourceEntity => $asked === $id
                ? $record
                : null);

        return new PageImages($resources, new PageMetadataBuilder(), 'https://www.example.test');
    }
}
