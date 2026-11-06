<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Factory;

use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\RowValues;
use Contenir\Seo\Mezzio\Exception\InvalidServiceException;
use Contenir\Seo\Mezzio\Sections\AssetRows;
use Contenir\Seo\Mezzio\Sections\ContentImage;
use Contenir\Seo\Mezzio\Sections\SectionMapFile;
use Contenir\Seo\Mezzio\Sections\SectionPageDefaults;
use Contenir\Seo\SeoConfig;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

use function class_exists;

/**
 * The page defaults the <seo> blocks in the site's admin.xml give, from
 * contenir_seo.admin_xml, config/admin.xml by default, with the parsed map
 * kept in contenir_seo.section_map_cache,
 * data/cache/contenir-seo-section-map.cache by default; false keeps no
 * cache and reads the admin.xml every time. contenir_seo.asset_entity names the entity a site's assets are
 * mapped with, which lets a record's first Section image stand in for a share image it has none of. A site without the file maps nothing. While the
 * records switch (SeoConfig::$records) is off, the defaults give only a title and a description.
 */
final class SectionPageDefaultsFactory
{
    /**
     * The first image of a record's Section field, which needs the entity a site's assets are mapped with.
     *
     * @throws ContainerExceptionInterface
     * @throws InvalidServiceException When the entity manager service has another type.
     */
    private static function contentImage(ContainerInterface $container, RowValues $rows, string $entity): ?ContentImage
    {
        if ('' === $entity || ! class_exists($entity)) {
            return null;
        }

        $em = Services::get($container, EntityManager::class, EntityManager::class);

        return new ContentImage((new AssetRows($em, $rows, $entity))->of(...));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws InvalidServiceException When the entity manager service has another type.
     */
    public function __invoke(ContainerInterface $container): SectionPageDefaults
    {
        $options = Options::fromContainer($container);
        $cache   = false === ($options->config[Options::KEY]['section_map_cache'] ?? null)
            ? null
            : $options->string('section_map_cache', 'data/cache/contenir-seo-section-map.cache');
        $rows = new RowValues(Services::get($container, EntityManager::class, EntityManager::class));
        $map  = (new SectionMapFile($options->string('admin_xml', 'config/admin.xml'), $cache))->load();

        $entity = $options->string('asset_entity', '');

        return new SectionPageDefaults(
            $map,
            $rows->of(...),
            Services::get($container, SeoConfig::class, SeoConfig::class)->records,
            self::contentImage($container, $rows, $entity),
        );
    }
}
