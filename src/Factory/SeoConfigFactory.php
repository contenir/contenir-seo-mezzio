<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Factory;

use Contenir\Seo\Mezzio\EnabledAddOns;
use Contenir\Seo\Mezzio\ShareImageSize;
use Contenir\Seo\Mezzio\SiteSettings;
use Contenir\Seo\SeoConfig;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Builds the site's SeoConfig from its settings (SiteSettings). Without the
 * SEO add-on enabled, only the core settings are read, so the site keeps
 * today's sitemap and robots.txt and nothing is hidden. The master switch for
 * what the <seo> blocks drive is extensions.seo.records in the settings, and
 * contenir_seo.records in the config where the settings don't have it; it is
 * off unless one turns it on. contenir_seo in the config can change where
 * things are:
 *
 *     'contenir_seo' => [
 *         'extensions_config' => 'config/autoload', // where extensions.global.php and .local.php are
 *         'public_path'       => 'public',          // the document root, to measure the share image
 *     ],
 *
 * @api
 */
final class SeoConfigFactory
{
    public const string HANDLE = 'seo';

    public const string EXTENSIONS_CONFIG = 'config/autoload';

    public const string PUBLIC_PATH = 'public';

    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): SeoConfig
    {
        $options  = Options::fromContainer($container);
        $settings = SiteSettings::fromConfig($options->config);
        $records  = $options->flag('records', false);
        $enabled  = EnabledAddOns::fromDirectory($options->string('extensions_config', self::EXTENSIONS_CONFIG));

        return (new ShareImageSize($options->string('public_path', self::PUBLIC_PATH)))->measure(
            $enabled->has(self::HANDLE)
                ? SeoConfig::fromSettings($settings, $records)
                : SeoConfig::fromCoreSettings($settings, $records),
        );
    }
}
