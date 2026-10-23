<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Integration;

use Contenir\Seo\Head\ShareImage;
use Contenir\Seo\Mezzio\ShareImageSize;
use Contenir\Seo\Mezzio\Tests\Trait\TempDirectoryTrait;
use Contenir\Seo\SeoConfig;
use Contenir\Seo\SiteUrl;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;
use function pack;

#[Group('integration')]
final class ShareImageSizeTest extends TestCase
{
    use TempDirectoryTrait;

    /**
     * @return array<string, array{?string}>
     */
    public static function unmeasured(): array
    {
        return [
            'no image'         => [null],
            'another host'     => ['https://cdn.example.net/img/share.png'],
            'a missing file'   => ['https://www.example.com/img/missing.png'],
            'not an image'     => ['https://www.example.com/img/notes.txt'],
            'outside the root' => ['https://www.example.com/img/../../secret.png'],
            'a directory'      => ['https://www.example.com/img'],
        ];
    }

    private static function config(?string $image): SeoConfig
    {
        return new SeoConfig(
            site: new SiteUrl('https://www.example.com'),
            shareImage: null === $image ? null : new ShareImage($image, 'Alt'),
        );
    }

    #[DataProvider('unmeasured')]
    #[Test]
    public function leavesTheConfigAloneWhenItCannotMeasure(?string $image): void
    {
        $config = self::config($image);

        static::assertSame($config, (new ShareImageSize("{$this->directory}/public/"))->measure($config));
    }

    #[Test]
    public function measuresAnImageOnTheSite(): void
    {
        $config = (new ShareImageSize("{$this->directory}/public"))->measure(self::config(
            'https://www.example.com/img/share%20image.png?v=2',
        ));

        static::assertSame(
            ['https://www.example.com/img/share%20image.png?v=2', 'Alt', 1200, 630],
            [
                $config->shareImage?->url,
                $config->shareImage?->alt,
                $config->shareImage?->width,
                $config->shareImage?->height,
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->makeTempDirectory();
        mkdir("{$this->directory}/public/img", recursive: true);
        file_put_contents(
            "{$this->directory}/public/img/share image.png",
            "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . pack('NN', 1200, 630) . "\x08\x02\x00\x00\x00"
                . pack('N', 0),
        );
        file_put_contents("{$this->directory}/public/img/notes.txt", data: 'Not an image');
        file_put_contents("{$this->directory}/secret.png", data: 'Outside');
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }
}
