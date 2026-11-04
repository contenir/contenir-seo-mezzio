<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Factory;

use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\RowValues;
use Contenir\Seo\Mezzio\Exception\InvalidServiceException;
use Contenir\Seo\Mezzio\Sections\SectionMapFile;
use Contenir\Seo\Mezzio\Sections\SectionPageDefaults;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * The page defaults the <seo> blocks in the site's admin.xml give, from
 * contenir_seo.admin_xml, config/admin.xml by default, with the parsed map
 * kept in contenir_seo.section_map_cache,
 * data/cache/contenir-seo-section-map.cache by default; false keeps no
 * cache and reads the admin.xml every time. A site without the file maps nothing.
 */
final class SectionPageDefaultsFactory
{
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

        return new SectionPageDefaults(
            (new SectionMapFile(
                $options->string('admin_xml', 'config/admin.xml'),
                $cache,
            ))->load(),
            $rows->of(...),
        );
    }
}
