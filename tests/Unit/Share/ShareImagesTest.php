<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Unit\Share;

use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Contenir\Resource\Core\Metadata\ImageSizeResolverInterface;
use Contenir\Resource\Core\Metadata\ImageUrlResolverInterface;
use Contenir\Resource\Core\Metadata\PathImageUrlResolver;
use Contenir\Seo\Mezzio\Factory\ShareImageSizesFactory;
use Contenir\Seo\Mezzio\Factory\ShareImageUrlsDelegator;
use Contenir\Seo\Mezzio\Share\ShareableImage;
use Contenir\Seo\Mezzio\Share\ShareImageSizes;
use Contenir\Seo\Mezzio\Share\ShareImageUrls;
use Contenir\Seo\Mezzio\Tests\TestAsset\ArrayContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;

#[Group('unit')]
final class ShareImagesTest extends TestCase
{
    private const string ORIGIN = 'https://www.site.test';

    /**
     * @return array<string, array{string, bool}>
     */
    public static function imageProvider(): array
    {
        return [
            'a JPEG path'         => ['/asset/library/a.jpg', true],
            'a long JPEG path'    => ['/asset/library/a.jpeg', true],
            'a PNG path'          => ['/asset/library/a.PNG', true],
            'a WebP path'         => ['/asset/library/a.webp', true],
            'a path with a query' => ['/asset/library/a.jpg?v=2', true],
            'an SVG'              => ['/asset/library/a.svg', false],
            'a GIF'               => ['/asset/library/a.gif', false],
            'no extension'        => ['/asset/library/a', false],
            'nothing'             => ['', false],
            'another site'        => ['https://cdn.test/a.jpg', false],
            'another site, short' => ['//cdn.test/a.jpg', false],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>|null}>
     */
    public static function noVariantProvider(): array
    {
        return [
            'none declared' => [null],
            'no height'     => [['width' => 1200, 'height' => 0, 'fit' => 'cover']],
            'no width'      => [['width' => 0, 'height' => 630, 'fit' => 'cover']],
            'not cropped'   => [['width' => 1200, 'height' => 630, 'fit' => 'contain']],
        ];
    }

    #[Test]
    public function asksTheResolverItWrapsAboutAnyOtherImage(): void
    {
        $inner = $this->createStub(ImageSizeResolverInterface::class);
        $inner->method('size')->willReturn([40, 30]);

        static::assertSame([40, 30], (new ShareImageSizes($inner, [1200, 630]))->size('/asset/a.svg'));
    }

    #[Test]
    public function asksTheResolverItWrapsWithoutAVariant(): void
    {
        $inner = $this->createStub(ImageSizeResolverInterface::class);
        $inner->method('size')->willReturn([40, 30]);

        static::assertSame([40, 30], (new ShareImageSizes($inner, null))->size('/asset/a.jpg'));
    }

    #[Test]
    #[DataProvider('imageProvider')]
    public function canHaveAVariantWhenOnTheSiteAndCroppable(string $image, bool $shareable): void
    {
        static::assertSame($shareable, ShareableImage::is($image));
    }

    #[Test]
    public function hasNoSizeWithNothingToAsk(): void
    {
        static::assertNull((new ShareImageSizes(null, null))->size('/asset/a.jpg'));
    }

    #[Test]
    public function leavesAnImageOnAnotherSiteAlone(): void
    {
        static::assertSame('https://cdn.test/a.jpg', $this->urls()->resolve('https://cdn.test/a.jpg', self::ORIGIN));
    }

    #[Test]
    public function pointsAPathAtItsVariant(): void
    {
        static::assertSame(
            'https://www.site.test/asset/library/project/_variant/share/a.jpg',
            $this->urls()->resolve('/asset/library/project/a.jpg', self::ORIGIN),
        );
    }

    #[Test]
    public function sizesAVariantFromItsDeclaration(): void
    {
        static::assertSame([1200, 630], (new ShareImageSizes(null, [1200, 630]))->size('/asset/a.jpg'));
    }

    /**
     * @param array<string, mixed>|null $share
     */
    #[Test]
    #[DataProvider('noVariantProvider')]
    public function theDelegatorLeavesTheSitesUrlsWithoutACroppedVariant(?array $share): void
    {
        static::assertSame(
            'https://www.site.test/asset/library/a.jpg',
            $this->delegated($share)->resolve('/asset/library/a.jpg', self::ORIGIN),
        );
    }

    #[Test]
    public function theDelegatorUsesTheVariantTheSiteNames(): void
    {
        $inner = new PathImageUrlResolver();

        static::assertSame(
            'https://www.site.test/asset/library/_variant/social/a.jpg',
            (new ShareImageUrlsDelegator())(
                $this->container(['social' => ['width' => 1200, 'height' => 630, 'fit' => 'cover']], 'social'),
                ImageUrlResolverInterface::class,
                static fn(): ImageUrlResolverInterface => $inner,
            )->resolve('/asset/library/a.jpg', self::ORIGIN),
        );
    }

    #[Test]
    public function theDelegatorWrapsTheSitesUrlsWhenItDeclaresTheVariant(): void
    {
        static::assertSame(
            'https://www.site.test/asset/library/_variant/share/a.jpg',
            $this->delegated(['width' => 1200, 'height' => 630, 'fit' => 'cover'])
                ->resolve('/asset/library/a.jpg', self::ORIGIN),
        );
    }

    #[Test]
    public function theFactoryReadsOtherImagesFromTheImageRoot(): void
    {
        $sizes = (new ShareImageSizesFactory())($this->container(
            [],
            'share',
            dirname(__DIR__, levels: 2) . '/TestAsset',
        ));

        static::assertSame([1, 1], $sizes->size('/pixel.png'));
    }

    #[Test]
    public function theFactorySizesTheVariant(): void
    {
        static::assertSame(
            [1200, 630],
            (new ShareImageSizesFactory())(
                $this->container(['share' => ['width' => 1200, 'height' => 630, 'fit' => 'cover']]),
            )
                ->size('/asset/a.jpg'),
        );
    }

    #[Test]
    public function theFactoryWithoutAnImageRootHasNoSizes(): void
    {
        static::assertNull((new ShareImageSizesFactory())($this->container([]))->size('/pixel.png'));
    }

    /**
     * @param array<string, mixed> $profiles
     */
    private function container(array $profiles, string $name = 'share', ?string $root = null): ArrayContainer
    {
        return new ArrayContainer([
            'config'                      => [
                'contenir_seo'      => ['share_variant' => $name],
                'contenir_resource' => null === $root ? [] : ['image_root' => $root],
            ],
            ProfileProviderService::class => new ProfileProviderService($profiles),
            AssetUrlBuilder::class        => new AssetUrlBuilder('/asset/library'),
        ]);
    }

    /**
     * @param array<string, mixed>|null $share
     */
    private function delegated(?array $share): ImageUrlResolverInterface
    {
        return (new ShareImageUrlsDelegator())(
            $this->container(null === $share ? [] : ['share' => $share]),
            ImageUrlResolverInterface::class,
            static fn(): ImageUrlResolverInterface => new PathImageUrlResolver(),
        );
    }

    private function urls(): ShareImageUrls
    {
        return new ShareImageUrls(new PathImageUrlResolver(), new AssetUrlBuilder('/asset/library'), 'share');
    }
}
