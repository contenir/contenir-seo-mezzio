<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Sitemap;

use Contenir\Seo\Sitemap\Page;

use function is_string;

/**
 * A routed page of the workflow navigation, whether it is discoverable, and its label.
 *
 * @internal
 */
final readonly class RoutedPage
{
    public function __construct(
        public Page $page,
        public bool $discoverable,
        public ?string $label = null,
    ) {}

    /**
     * A navigation page's label, or none when it has no text
     */
    public static function label(mixed $label): ?string
    {
        return is_string($label) && '' !== $label ? $label : null;
    }
}
