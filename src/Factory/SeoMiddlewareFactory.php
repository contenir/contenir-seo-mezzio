<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Factory;

use Contenir\Seo\Mezzio\CurrentRequest;
use Contenir\Seo\Mezzio\SeoMiddleware;
use Contenir\Seo\Mezzio\Sitemap\SitemapResponses;
use Contenir\Seo\SeoConfig;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * @api
 */
final class SeoMiddlewareFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): SeoMiddleware
    {
        return new SeoMiddleware(
            Services::get($container, SeoConfig::class, SeoConfig::class),
            Services::get($container, CurrentRequest::class, CurrentRequest::class),
            Services::get($container, SitemapResponses::class, SitemapResponses::class),
        );
    }
}
