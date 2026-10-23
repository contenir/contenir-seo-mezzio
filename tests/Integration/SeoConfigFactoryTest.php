<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Integration;

use Contenir\Seo\Mezzio\Factory\SeoConfigFactory;
use Contenir\Seo\Mezzio\Tests\TestAsset\ArrayContainer;
use Contenir\Seo\Mezzio\Tests\Trait\TempDirectoryTrait;
use Contenir\Seo\Visibility\Visibility;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;
use function pack;

/**
 * The factory reading a site's settings, its enabled add-ons and its share
 * image from files, as a deployed site has them.
 */
#[Group('integration')]
final class SeoConfigFactoryTest extends TestCase
{
    use TempDirectoryTrait;

    #[Test]
    public function measuresTheShareImageInThePublicDirectory(): void
    {
        $this->writeConfig('extensions.global.php', ['extensions' => ['enabled' => ['seo']]]);
        $this->writeImage('/asset/img/seo/share.png', 1200, 630);

        $config = (new SeoConfigFactory())(new ArrayContainer(['config' => $this->config()]));

        static::assertSame(
            ['https://www.example.com/asset/img/seo/share.png', 'The workshop', 1200, 630, Visibility::Hide],
            [
                $config->shareImage?->url,
                $config->shareImage?->alt,
                $config->shareImage?->width,
                $config->shareImage?->height,
                $config->visibility,
            ],
        );
    }

    #[Test]
    public function readsOnlyTheCoreSettingsWithoutTheAddOn(): void
    {
        $this->writeConfig('extensions.global.php', ['extensions' => ['enabled' => ['tracking']]]);

        $config = (new SeoConfigFactory())(new ArrayContainer(['config' => $this->config()]));

        static::assertSame(
            ['https://www.example.com', null, Visibility::Show],
            [$config->site->origin, $config->shareImage, $config->visibility],
        );
    }

    #[Test]
    public function readsSettingsAtTheTopLevelWithTheLocalOnesLaidOver(): void
    {
        $this->writeConfig('extensions.local.php', ['extensions' => ['enabled' => ['seo']]]);

        $config = (new SeoConfigFactory())(new ArrayContainer([
            'config' => [
                'site'         => ['url' => 'https://www.example.com', 'title' => 'Example'],
                'extensions'   => ['seo' => ['title_separator' => '–']],
                'settings'     => ['site' => ['url' => 'http://example.local']],
                'contenir_seo' => ['extensions_config' => $this->directory],
            ],
        ]));

        static::assertSame(
            ['http://example.local', 'About – Example'],
            [$config->site->origin, $config->title->format('About')],
        );
    }

    #[Test]
    public function readsTheDefaultsFromAConfigServiceThatIsNotAList(): void
    {
        static::assertSame('', (new SeoConfigFactory())(new ArrayContainer(['config' => 'none']))->site->origin);
    }

    #[Test]
    public function readsTheDefaultsFromConfigThatIsNotAList(): void
    {
        $config = (new SeoConfigFactory())(new ArrayContainer([
            'config' => ['settings' => 'none', 'contenir_seo' => ['extensions_config' => 3, 'public_path' => 4]],
        ]));

        static::assertSame(['', Visibility::Show], [$config->site->origin, $config->visibility]);
    }

    #[Test]
    public function readsTheDefaultsWithoutConfig(): void
    {
        $config = (new SeoConfigFactory())(new ArrayContainer([]));

        static::assertSame(['', true, Visibility::Show], [
            $config->site->origin,
            $config->sitemap->enabled,
            $config->visibility,
        ]);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->makeTempDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return [
            'settings'     => [
                'site'       => ['url' => 'https://www.example.com', 'title' => 'Example'],
                'extensions' => ['seo' => [
                    'visibility'      => 'hide',
                    'share_image'     => '/asset/img/seo/share.png',
                    'share_image_alt' => 'The workshop',
                ]],
            ],
            'contenir_seo' => ['extensions_config' => $this->directory, 'public_path' => "{$this->directory}/public/"],
        ];
    }

    /**
     * A PNG header, which is all getimagesize() reads.
     */
    private function writeImage(string $path, int $width, int $height): void
    {
        mkdir("{$this->directory}/public/asset/img/seo", recursive: true);
        file_put_contents(
            "{$this->directory}/public{$path}",
            "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . pack('NN', $width, $height) . "\x08\x02\x00\x00\x00"
                . pack('N', 0),
        );
    }
}
