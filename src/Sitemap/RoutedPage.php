<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Sitemap;

use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Seo\Sitemap\Page;

use function is_string;

/**
 * A routed page of the workflow navigation, whether it is discoverable, and its label.
 *
 * @internal
 */
final readonly class RoutedPage
{
    /**
     * @param mixed $route The page's workflow route, as the strategy gives it
     */
    public function __construct(
        public Page $page,
        public bool $discoverable,
        public ?string $label = null,
        public mixed $route = null,
    ) {}

    /**
     * A navigation page's label, or none when it has no text
     */
    public static function label(mixed $label): ?string
    {
        return is_string($label) && '' !== $label ? $label : null;
    }

    /**
     * The page as the sitemap lists it: with its record's share image, given the site's images.
     *
     * @throws DbModelException When the page's record cannot be read.
     */
    public function listed(?PageImages $images): Page
    {
        return $images?->add($this->page, $this->route) ?? $this->page;
    }
}
