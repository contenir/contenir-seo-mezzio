<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Share;

use function in_array;
use function is_string;
use function parse_url;
use function pathinfo;
use function str_starts_with;
use function strtolower;

use const PATHINFO_EXTENSION;
use const PHP_URL_PATH;
use const PHP_URL_SCHEME;

/**
 * Whether an image can have a share variant: a path on the site, since a variant
 * is made beside its original, of a kind that can be cropped and that sharing
 * sites read.
 *
 * @internal
 */
final class ShareableImage
{
    private const array EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public static function is(string $image): bool
    {
        $path = parse_url($image, PHP_URL_PATH);

        return (
            null === parse_url($image, PHP_URL_SCHEME)
                && ! str_starts_with($image, '//')
                && '' !== $image
                && is_string($path)
                && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, strict: true)
        );
    }
}
