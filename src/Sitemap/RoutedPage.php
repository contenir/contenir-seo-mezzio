<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Sitemap;

use Contenir\Seo\Sitemap\Page;

/**
 * A routed page of the workflow navigation, and whether it is discoverable.
 *
 * @internal
 */
final readonly class RoutedPage
{
    public function __construct(
        public Page $page,
        public bool $discoverable,
    ) {}
}
