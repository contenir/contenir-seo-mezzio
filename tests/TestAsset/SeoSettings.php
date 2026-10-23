<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\TestAsset;

use Contenir\Seo\SeoConfig;

use function array_replace_recursive;

/**
 * Site settings as the CMS writes them, and the SeoConfig they make.
 */
final class SeoSettings
{
    /**
     * @param array<array-key, mixed> $overrides
     */
    public static function config(array $overrides = []): SeoConfig
    {
        return SeoConfig::fromSettings(self::settings($overrides));
    }

    /**
     * @param array<array-key, mixed> $overrides
     *
     * @return array<array-key, mixed>
     */
    public static function settings(array $overrides = []): array
    {
        return array_replace_recursive([
            'site'       => ['url' => 'https://www.example.test', 'title' => 'Example'],
            'extensions' => ['seo' => ['visibility' => 'show']],
        ], $overrides);
    }
}
