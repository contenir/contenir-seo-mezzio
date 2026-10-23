<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio;

use Contenir\Seo\Sitemap\Page;

/**
 * Pages for the sitemap that aren't in the site's navigation, such as news
 * articles or products. Register an implementation as a service and name it
 * in contenir_seo.sitemap_sources.
 *
 * @api
 */
interface SitemapSourceInterface
{
    /**
     * @return iterable<Page>
     */
    public function pages(): iterable;
}
