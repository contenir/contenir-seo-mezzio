<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Sections;

use Closure;
use Contenir\Metadata\MetadataInterface;
use Contenir\Resource\Core\Entity\AbstractResourceEntity;
use Contenir\Resource\Core\Metadata\PageDefaults;
use Contenir\Resource\Core\Metadata\PageDefaultsInterface;
use Contenir\Resource\Core\Metadata\PageOverrides;
use Contenir\Seo\Sections\SectionMap;
use Override;

/**
 * A resource's page defaults from the <seo> block its type has in the site's
 * admin.xml, read from the resource's own columns, with the record's own
 * overrides from its advanced column, which win over the page's. A resource whose type no
 * block maps, or that isn't a resource entity, has none.
 */
final readonly class SectionPageDefaults implements PageDefaultsInterface
{
    /**
     * @param Closure(object): array<string, mixed> $row A resource's columns by name
     */
    public function __construct(
        private SectionMap $map,
        private Closure $row,
    ) {}

    #[Override]
    public function for(MetadataInterface $resource): ?PageDefaults
    {
        $block = $resource instanceof AbstractResourceEntity ? $this->map->for($resource->getType()) : null;
        if (null === $block) {
            return null;
        }

        $page = $block->page(($this->row)($resource));

        return new PageDefaults(
            title: $page->title,
            description: $page->description,
            image: $page->image,
            imageAlt: $page->imageAlt,
            canonical: $page->canonical,
            robots: $page->robots,
            schema: $page->schema,
            overrides: new PageOverrides(
                $page->overrides->title,
                $page->overrides->description,
                $page->overrides->image,
                $page->overrides->imageAlt,
            ),
        );
    }
}
