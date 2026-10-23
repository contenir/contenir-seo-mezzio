<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\TestAsset;

use Contenir\Seo\Mezzio\SitemapSourceInterface;
use Contenir\Seo\Sitemap\Page;
use Override;

/**
 * A sitemap source with fixed pages.
 */
final readonly class FixedPages implements SitemapSourceInterface
{
    /**
     * @param list<Page> $pages
     */
    public function __construct(
        private array $pages = [],
    ) {}

    /**
     * Pages at /page-1, /page-2… up to $count.
     */
    public static function numbered(int $count): self
    {
        $pages = [];
        for ($number = 1; $number <= $count; ++$number) {
            $pages[] = new Page("/page-{$number}");
        }

        return new self($pages);
    }

    #[Override]
    public function pages(): array
    {
        return $this->pages;
    }
}
