<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Factory;

use Contenir\Seo\Mezzio\Sitemap\SitemapResponses;
use Contenir\Seo\Mezzio\Sitemap\WorkflowNavigationPages;
use Contenir\Seo\Mezzio\SitemapSourceInterface;
use Contenir\Seo\SeoConfig;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Builds the sitemap and robots.txt responses, with the PSR-17 factories and
 * the sitemap's sources: the workflow navigation (unless
 * contenir_seo.sitemap_navigation is false), then each service named in
 * contenir_seo.sitemap_sources.
 *
 * @api
 */
final class SitemapResponsesFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): SitemapResponses
    {
        $options = Options::fromContainer($container);
        $names   = $options->strings('sitemap_sources', []);
        if ($options->flag('sitemap_navigation', true)) {
            $names = [WorkflowNavigationPages::class, ...$names];
        }

        $sources = [];
        foreach ($names as $name) {
            $sources[] = Services::get($container, $name, SitemapSourceInterface::class);
        }

        return new SitemapResponses(
            Services::get($container, SeoConfig::class, SeoConfig::class),
            Services::get($container, ResponseFactoryInterface::class, ResponseFactoryInterface::class),
            Services::get($container, StreamFactoryInterface::class, StreamFactoryInterface::class),
            $sources,
        );
    }
}
