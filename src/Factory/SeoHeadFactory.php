<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Factory;

use Contenir\Seo\Mezzio\CurrentRequest;
use Contenir\Seo\Mezzio\SeoHead;
use Contenir\Seo\SeoConfig;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Builds SeoHead, with the home page's paths from contenir_seo.home_paths
 * (["/"] by default).
 *
 * @api
 */
final class SeoHeadFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): SeoHead
    {
        return new SeoHead(
            Services::get($container, SeoConfig::class, SeoConfig::class),
            Services::get($container, CurrentRequest::class, CurrentRequest::class),
            Options::fromContainer($container)->strings('home_paths', ['/']),
        );
    }
}
