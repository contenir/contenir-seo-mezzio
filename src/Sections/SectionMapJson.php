<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Sections;

use Contenir\Seo\Sections\SectionMap;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * A section map's blocks as the JSON its cache file holds, beside the stamp
 * of the admin.xml they were read from. Each block is written with its
 * public parts, which SeoBlockJson reads back.
 */
final class SectionMapJson
{
    /**
     * The map $json holds for $stamp, or null when it holds another
     * admin.xml's, or isn't a map's.
     */
    public static function decode(string $json, string $stamp): ?SectionMap
    {
        /** @var mixed $cached */
        $cached = json_decode($json, associative: true);
        if (! is_array($cached) || $stamp !== ($cached['stamp'] ?? null) || ! is_array($cached['blocks'] ?? null)) {
            return null;
        }

        $blocks = [];
        /** @var mixed $block */
        foreach ($cached['blocks'] as $type => $block) {
            if (! is_string($type) || ! is_array($block)) {
                return null;
            }

            $blocks[$type] = SeoBlockJson::decode($type, $block);
        }

        return new SectionMap($blocks);
    }

    public static function encode(string $stamp, SectionMap $map): ?string
    {
        $json = json_encode(['stamp' => $stamp, 'blocks' => $map->blocks]);

        return false === $json ? null : $json;
    }
}
