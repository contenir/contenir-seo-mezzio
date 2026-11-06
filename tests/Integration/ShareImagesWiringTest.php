<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Integration;

use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Contenir\Resource\Core\ConfigProvider as ResourceConfigProvider;
use Contenir\Resource\Core\Metadata\ImageSizeResolverInterface;
use Contenir\Resource\Core\Metadata\ImageUrlResolverInterface;
use Contenir\Resource\Core\Metadata\PathImageUrlResolver;
use Contenir\Seo\Mezzio\ConfigProvider;
use Contenir\Seo\SeoConfig;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_merge_recursive;

/**
 * The share variant as a real container wires it: contenir-resource aliases its
 * image URL resolver, and a delegator goes by the name the alias leads to.
 */
#[Group('integration')]
final class ShareImagesWiringTest extends TestCase
{
    private const string ORIGIN = 'https://www.site.test';

    private const array RECORDS = ['contenir_seo' => ['records' => true]];

    private const array SHARE = ['share' => ['width' => 1200, 'height' => 630, 'fit' => 'cover']];

    /**
     * @param array<string, mixed> $profiles
     * @param array<string, mixed> $config
     */
    private static function container(array $profiles, array $config = self::RECORDS): ServiceManager
    {
        $resource  = (new ResourceConfigProvider())->getDependencies();
        $container = new ServiceManager(array_merge_recursive(
            (new ConfigProvider())->getDependencies(),
            [
                'aliases'   => [
                    ImageUrlResolverInterface::class => $resource['aliases'][ImageUrlResolverInterface::class],
                ],
                'factories' => [
                    PathImageUrlResolver::class => $resource['factories'][PathImageUrlResolver::class],
                ],
            ],
        ));
        $container->setService('config', $config);
        $container->setService(ProfileProviderService::class, new ProfileProviderService($profiles));
        $container->setService(AssetUrlBuilder::class, new AssetUrlBuilder('/asset/library'));

        return $container;
    }

    #[Test]
    public function aResolverASiteRegistersUnderTheInterfaceIsWrappedToo(): void
    {
        $site = new ServiceManager([
            'factories'  => [
                ImageUrlResolverInterface::class => static fn(): ImageUrlResolverInterface => new PathImageUrlResolver(),
                SeoConfig::class                 => (new ConfigProvider())->getDependencies()['factories'][SeoConfig::class],
            ],
            'delegators' => (new ConfigProvider())->getDependencies()['delegators'],
            'services'   => [
                'config'                      => self::RECORDS,
                ProfileProviderService::class => new ProfileProviderService(self::SHARE),
                AssetUrlBuilder::class        => new AssetUrlBuilder('/asset/library'),
            ],
        ]);

        static::assertSame(
            'https://www.site.test/asset/library/_variant/share/a.jpg',
            $site->get(ImageUrlResolverInterface::class)->resolve('/asset/library/a.jpg', self::ORIGIN),
        );
    }

    #[Test]
    public function aSiteWithoutTheVariantSendsItsOriginal(): void
    {
        static::assertSame(
            'https://www.site.test/asset/library/a.jpg',
            self::container([])
                ->get(ImageUrlResolverInterface::class)
                ->resolve('/asset/library/a.jpg', self::ORIGIN),
        );
    }

    #[Test]
    public function aSiteWithTheRecordsSwitchOffSendsItsOriginalEvenWithTheVariant(): void
    {
        static::assertSame(
            'https://www.site.test/asset/library/a.jpg',
            self::container(self::SHARE, ['contenir_seo' => ['records' => false]])
                ->get(ImageUrlResolverInterface::class)
                ->resolve('/asset/library/a.jpg', self::ORIGIN),
        );
    }

    #[Test]
    public function aSiteWithTheVariantSendsIt(): void
    {
        static::assertSame(
            'https://www.site.test/asset/library/_variant/share/a.jpg',
            self::container(self::SHARE)
                ->get(ImageUrlResolverInterface::class)
                ->resolve('/asset/library/a.jpg', self::ORIGIN),
        );
    }

    #[Test]
    public function theVariantsSizeIsWhatThePageSays(): void
    {
        static::assertSame(
            [1200, 630],
            self::container(self::SHARE)->get(ImageSizeResolverInterface::class)->size('/asset/library/a.jpg'),
        );
    }

    #[Test]
    public function withTheRecordsSwitchOffNoVariantSizeIsClaimed(): void
    {
        static::assertNull(
            self::container(self::SHARE, ['contenir_seo' => ['records' => false]])
                ->get(ImageSizeResolverInterface::class)
                ->size('/asset/library/a.jpg'),
        );
    }
}
