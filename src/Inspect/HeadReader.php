<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Inspect;

use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;

use function array_filter;
use function array_merge;
use function array_values;
use function is_array;
use function json_decode;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function trim;

use const LIBXML_NONET;

/**
 * What a rendered page's head tells search engines and sharing sites: its
 * title, description, share image and its alt text, canonical address,
 * robots directive and JSON-LD nodes, as the page wrote them.
 */
final class HeadReader
{
    /**
     * @return array{
     *     title: ?string,
     *     description: ?string,
     *     image: ?string,
     *     imageAlt: ?string,
     *     canonical: ?string,
     *     robots: ?string,
     *     jsonLd: list<array<array-key, mixed>>
     * }
     */
    public static function read(string $html): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // libxml reads HTML as Latin-1 unless told otherwise, and the sites send UTF-8
        $document->loadHTML("<?xml encoding=\"UTF-8\">{$html}", LIBXML_NONET);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);

        return [
            'title'       => self::text($xpath, '//title'),
            'description' => self::text($xpath, '//meta[@name="description"]/@content'),
            'image'       => self::text($xpath, '//meta[@property="og:image"]/@content'),
            'imageAlt'    => self::text($xpath, '//meta[@property="og:image:alt"]/@content'),
            'canonical'   => self::text($xpath, '//link[@rel="canonical"]/@href'),
            'robots'      => self::text($xpath, '//meta[@name="robots"]/@content'),
            'jsonLd'      => self::jsonLd($xpath),
        ];
    }

    /**
     * Every JSON-LD node the page writes, a graph's nodes one by one.
     *
     * @return list<array<array-key, mixed>>
     */
    private static function jsonLd(DOMXPath $xpath): array
    {
        $found = [];
        /** @var DOMNodeList<DOMNode>|false $scripts */
        $scripts = $xpath->query('//script[@type="application/ld+json"]');
        foreach (false === $scripts ? [] : $scripts as $script) {
            $found[] = self::nodes(json_decode($script->textContent, associative: true));
        }

        return array_merge(...$found);
    }

    /**
     * The nodes one decoded script holds: its graph's, or itself.
     *
     * @return list<array<array-key, mixed>>
     */
    private static function nodes(mixed $data): array
    {
        /** @var mixed $graph */
        $graph = is_array($data) ? $data['@graph'] ?? [$data] : [];

        return is_array($graph) ? array_values(array_filter($graph, is_array(...))) : [];
    }

    /**
     * The first match's text, trimmed, or null when there is none or it is empty.
     */
    private static function text(DOMXPath $xpath, string $query): ?string
    {
        /** @var DOMNodeList<DOMNode>|false $found */
        $found = $xpath->query($query);
        $text  = false === $found ? '' : trim((string) $found->item(0)?->textContent);

        return '' === $text ? null : $text;
    }
}
