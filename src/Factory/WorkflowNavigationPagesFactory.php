<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Factory;

use Contenir\Resource\Core\Metadata\PageMetadataBuilder;
use Contenir\Resource\Core\ResourceManagerInterface;
use Contenir\Seo\Mezzio\Sitemap\PageImages;
use Contenir\Seo\Mezzio\Sitemap\WorkflowNavigationPages;
use Contenir\Seo\SeoConfig;
use Contenir\Workflow\Container\WorkflowConfig;
use Contenir\Workflow\Strategy\ResourceStrategy;
use Contenir\Workflow\Strategy\ResourceStrategyInterface;
use Mezzio\Router\RouterInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Builds the workflow navigation's sitemap pages from the strategy that
 * workflow_manager.strategy names, as contenir-workflow-mezzio does, and the
 * Mezzio router. Where the site has contenir-resource's resource manager, each
 * page lists its record's share image, unless `sitemap_images` is false.
 *
 * @api
 */
final class WorkflowNavigationPagesFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): WorkflowNavigationPages
    {
        return new WorkflowNavigationPages(
            Services::get(
                $container,
                WorkflowConfig::fromContainerOrEmpty($container)->stringOr('strategy', ResourceStrategy::class),
                ResourceStrategyInterface::class,
            ),
            Services::get($container, RouterInterface::class, RouterInterface::class),
            Options::fromContainer($container)->flag('sitemap_images', true)
            && $container->has(ResourceManagerInterface::class)
                ? new PageImages(
                    Services::get($container, ResourceManagerInterface::class, ResourceManagerInterface::class),
                    Services::get($container, PageMetadataBuilder::class, PageMetadataBuilder::class),
                    Services::get($container, SeoConfig::class, SeoConfig::class)->site->origin,
                )
                : null,
        );
    }
}
