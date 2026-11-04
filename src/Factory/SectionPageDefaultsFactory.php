<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Factory;

use Contenir\Db\Model\EntityManager;
use Contenir\Db\Model\RowValues;
use Contenir\Seo\Mezzio\Exception\InvalidServiceException;
use Contenir\Seo\Mezzio\Sections\SectionPageDefaults;
use Contenir\Seo\Sections\SectionMap;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

use function file_get_contents;
use function is_file;

/**
 * The page defaults the <seo> blocks in the site's admin.xml give, from
 * contenir_seo.admin_xml, config/admin.xml by default. A site without the
 * file maps nothing.
 */
final class SectionPageDefaultsFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws InvalidServiceException When the entity manager service has another type.
     */
    public function __invoke(ContainerInterface $container): SectionPageDefaults
    {
        $path = Options::fromContainer($container)->string('admin_xml', 'config/admin.xml');
        $rows = new RowValues(Services::get($container, EntityManager::class, EntityManager::class));

        return new SectionPageDefaults(
            SectionMap::fromAdminXml(is_file($path) ? (string) file_get_contents($path) : ''),
            $rows->of(...),
        );
    }
}
