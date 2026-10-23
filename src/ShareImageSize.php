<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio;

use Contenir\Seo\SeoConfig;

use function getimagesize;
use function is_file;
use function is_string;
use function parse_url;
use function rawurldecode;
use function str_contains;

use const PHP_URL_PATH;

/**
 * Measures the default share image when it is a file under the site's
 * public directory, so share previews get og:image:width and height.
 *
 * @api
 */
final readonly class ShareImageSize
{
    /**
     * @param string $publicPath The site's document root, such as "public"
     */
    public function __construct(
        private string $publicPath,
    ) {}

    /**
     * $config with its share image measured, or as it is when the image
     * isn't a readable image file on the site.
     */
    public function measure(SeoConfig $config): SeoConfig
    {
        $image = $config->shareImage;
        if (null === $image || ! $config->site->owns($image->url)) {
            return $config;
        }

        $path = parse_url($image->url, PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '';
        $file = $this->publicPath . $path;
        if (str_contains($path, '..') || ! is_file($file)) {
            return $config;
        }

        $size = getimagesize($file);

        return false === $size ? $config : $config->withShareImage($image->withSize($size[0], $size[1]));
    }
}
