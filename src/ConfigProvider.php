<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio;

use Contenir\Seo\JsonLd\Graph;
use Contenir\Seo\Mezzio\Factory\JsonLdHelperFactory;
use Contenir\Seo\Mezzio\Factory\SeoConfigFactory;
use Contenir\Seo\Mezzio\Factory\SeoHeadFactory;
use Contenir\Seo\Mezzio\Factory\SeoHelperFactory;
use Contenir\Seo\Mezzio\Factory\SeoMiddlewareFactory;
use Contenir\Seo\Mezzio\Factory\SitemapResponsesFactory;
use Contenir\Seo\Mezzio\Factory\WorkflowNavigationPagesFactory;
use Contenir\Seo\Mezzio\Sitemap\SitemapResponses;
use Contenir\Seo\Mezzio\Sitemap\WorkflowNavigationPages;
use Contenir\Seo\Mezzio\View\JsonLdHelper;
use Contenir\Seo\Mezzio\View\SeoHelper;
use Contenir\Seo\SeoConfig;

/**
 * The package's services and its seo() and jsonLd() view helpers. Nothing changes on the
 * site until SeoMiddleware is piped.
 *
 * @api
 */
final class ConfigProvider
{
    /**
     * @return array{
     *     invokables: array<class-string, class-string>,
     *     factories: array<class-string, class-string>,
     * }
     */
    public function getDependencies(): array
    {
        return [
            'invokables' => [CurrentRequest::class => CurrentRequest::class, Graph::class => Graph::class],
            'factories'  => [
                SeoConfig::class               => SeoConfigFactory::class,
                SeoHead::class                 => SeoHeadFactory::class,
                SeoMiddleware::class           => SeoMiddlewareFactory::class,
                SitemapResponses::class        => SitemapResponsesFactory::class,
                WorkflowNavigationPages::class => WorkflowNavigationPagesFactory::class,
            ],
        ];
    }

    /**
     * @return array{
     *     aliases: array<string, class-string>,
     *     factories: array<class-string, class-string>,
     * }
     */
    public function getViewHelpers(): array
    {
        return [
            'aliases'   => ['jsonLd' => JsonLdHelper::class, 'seo' => SeoHelper::class],
            'factories' => [
                JsonLdHelper::class => JsonLdHelperFactory::class,
                SeoHelper::class    => SeoHelperFactory::class,
            ],
        ];
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'view_helpers' => $this->getViewHelpers(),
        ];
    }
}
