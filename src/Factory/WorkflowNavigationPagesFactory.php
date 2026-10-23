<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Factory;

use Contenir\Seo\Mezzio\Sitemap\WorkflowNavigationPages;
use Contenir\Workflow\Container\WorkflowConfig;
use Contenir\Workflow\Strategy\ResourceStrategy;
use Contenir\Workflow\Strategy\ResourceStrategyInterface;
use Mezzio\Router\RouterInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Builds the workflow navigation's sitemap pages from the strategy that
 * workflow_manager.strategy names, as contenir-workflow-mezzio does, and the
 * Mezzio router.
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
        );
    }
}
