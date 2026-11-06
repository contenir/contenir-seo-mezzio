<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Sections;

use Contenir\Seo\Sections\SeoBlock;

use function array_filter;
use function array_values;
use function is_array;
use function is_string;

/**
 * One <seo> block read back from its section map's cache. A part the cache
 * holds in another shape reads as none.
 */
final class SeoBlockJson
{
    /**
     * @param array<array-key, mixed> $block
     */
    public static function decode(string $type, array $block): SeoBlock
    {
        /** @var array<string, list<string>> $roles */
        $roles = is_array($block['roles'] ?? null) ? $block['roles'] : [];
        /** @var list<string> $required */
        $required = is_array($block['required'] ?? null) ? $block['required'] : [];

        return new SeoBlock(
            $type,
            is_string($block['schema'] ?? null) ? $block['schema'] : null,
            $roles,
            $required,
            is_string($block['advanced'] ?? null) ? $block['advanced'] : null,
            false !== ($block['automatic'] ?? true),
            is_array($block['content'] ?? null)
                ? array_values(array_filter($block['content'], is_string(...)))
                : SeoBlock::CONTENT_ROLES,
        );
    }
}
