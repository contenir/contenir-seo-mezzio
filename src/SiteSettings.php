<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio;

use function array_replace_recursive;
use function is_array;

/**
 * A Mezzio site's settings, as the CMS writes them: config/autoload's
 * settings.global.php merges at the top level of the application's config,
 * and settings.local.php under "settings", which is laid over it.
 *
 * @api
 */
final class SiteSettings
{
    /**
     * @param array<array-key, mixed> $config The application's config
     *
     * @return array<array-key, mixed>
     *
     * @mago-expect analysis:mixed-assignment The application's config is untyped; the value is checked here.
     */
    public static function fromConfig(array $config): array
    {
        $local = $config['settings'] ?? null;

        return is_array($local) ? array_replace_recursive($config, $local) : $config;
    }
}
