<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Sections;

use Closure;
use Contenir\Metadata\MetadataInterface;
use Contenir\Resource\Core\Entity\AbstractResourceEntity;
use Contenir\Resource\Core\Metadata\PageDefaults;
use Contenir\Resource\Core\Metadata\PageDefaultsInterface;
use Contenir\Resource\Core\Metadata\PageOverrides;
use Contenir\Seo\Sections\ContentBlocks;
use Contenir\Seo\Sections\LinkedValue;
use Contenir\Seo\Sections\SectionMap;
use Override;

use function is_iterable;
use function is_object;

/**
 * A resource's page defaults from the <seo> block its type has in the site's
 * admin.xml, read from the resource's own columns and the records it links to,
 * with the record's own overrides from its advanced column, which win over the
 * page's, and, where its columns give no description or image, its Section field's. A resource whose type no block maps, or that isn't a resource entity,
 * has none. A link whose relation the entity doesn't have gives no value. With the
 * records switch off, only the title, the description and the share image as the site stores it
 * are given, from the block's columns and the record's own advanced column; the image's
 * alt text, canonical address, robots and schema stay out.
 */
final readonly class SectionPageDefaults implements PageDefaultsInterface
{
    /**
     * @param Closure(object): array<string, mixed> $row A record's columns by name
     */
    public function __construct(
        private SectionMap $map,
        private Closure $row,
        private bool $records = true,
        private ?ContentImage $content = null,
    ) {}

    /**
     * Only the title, description and plain share image of $defaults, and the record's own overrides of the first two.
     */
    private static function headline(PageDefaults $defaults): PageDefaults
    {
        return new PageDefaults(
            title: $defaults->title,
            description: $defaults->description,
            image: $defaults->image,
            overrides: new PageOverrides($defaults->overrides->title, $defaults->overrides->description),
        );
    }

    #[Override]
    public function for(MetadataInterface $resource): ?PageDefaults
    {
        $block = $resource instanceof AbstractResourceEntity ? $this->map->for($resource->getType()) : null;
        if (null === $block) {
            return null;
        }

        $row  = ($this->row)($resource);
        $page = $block->page([
            ...$row,
            ...$this->linked($resource, $block->linked()),
            ...$this->contentImage($row),
        ]);
        $defaults = new PageDefaults(
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

        return $this->records ? $defaults : self::headline($defaults);
    }

    /**
     * The first image of the record's Section field, under the key the block reads it from, when the site can
     * look its assets up.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, string>
     */
    private function contentImage(array $row): array
    {
        $path = $this->content?->pathOf($row);

        return null === $path ? [] : [ContentBlocks::IMAGE => $path];
    }

    /**
     * Each linked value's text from the records $resource links to, by how the block names it.
     *
     * @param array<string, LinkedValue> $links
     *
     * @return array<string, ?string>
     *
     * @mago-expect analysis:string-member-selector A block names the relation, as a site's entity declares it.
     */
    private function linked(object $resource, array $links): array
    {
        $values = [];
        foreach ($links as $source => $link) {
            $values[$source] = $link->from($this->rows($resource->{$link->relation} ?? null));
        }

        return $values;
    }

    /**
     * The rows of what a relation holds: a collection's records, or the one record.
     *
     * @return iterable<array<string, mixed>>
     */
    private function rows(mixed $related): iterable
    {
        /** @var mixed $record */
        foreach (is_iterable($related) ? $related : [$related] as $record) {
            if (! is_object($record)) {
                continue;
            }

            yield ($this->row)($record);
        }
    }
}
