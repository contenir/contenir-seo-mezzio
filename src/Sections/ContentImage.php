<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Sections;

use Closure;
use Contenir\Seo\Sections\ContentBlocks;

use function is_scalar;
use function trim;

/**
 * The path of the first image a record's Section field shows, from the assets its first media block names,
 * leaving out an asset that isn't active or isn't an image.
 */
final readonly class ContentImage
{
    /**
     * @param Closure(list<string>): array<string, array<string, mixed>> $assets The assets' columns by name, by id
     *     (the closure may throw the db model's exceptions)
     */
    public function __construct(
        private Closure $assets,
    ) {}

    /**
     * @param array<string, mixed> $asset
     */
    private static function path(array $asset): ?string
    {
        /** @var mixed $active */
        $active = $asset['active'] ?? null;
        /** @var mixed $type */
        $type = $asset['type'] ?? null;
        /** @var mixed $path */
        $path = $asset['path'] ?? null;
        if (
            [] === $asset
            || (
                null !== $active
                && 'active' !== $active
            )
            || (
                null !== $type
                && 'image' !== $type
            )
            || ! is_scalar($path)
        ) {
            return null;
        }

        return '' === trim((string) $path) ? null : (string) $path;
    }

    /**
     * @param array<string, mixed> $row A record's columns by name
     */
    public function pathOf(array $row): ?string
    {
        $ids = ContentBlocks::of($row[ContentBlocks::COLUMN] ?? null)->imageIds();
        if ([] === $ids) {
            return null;
        }

        $assets = ($this->assets)($ids);
        foreach ($ids as $id) {
            $path = self::path($assets[$id] ?? []);
            if (null !== $path) {
                return $path;
            }
        }

        return null;
    }
}
