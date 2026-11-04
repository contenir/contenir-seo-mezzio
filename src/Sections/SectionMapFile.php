<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Sections;

use Contenir\Seo\Sections\SectionMap;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function filesize;
use function is_dir;
use function is_file;
use function mkdir;
use function rename;
use function sprintf;
use function uniqid;

/**
 * The section map of a site's admin.xml, kept in a cache file so a request
 * reads the file only when it has changed: the cache holds the map's blocks
 * as JSON with the admin.xml's modification time and size, and a cache for
 * another version, or one that can't be read, is rebuilt. Without a cache
 * file, the admin.xml is read every time; without an admin.xml, nothing is
 * mapped.
 */
final readonly class SectionMapFile
{
    public function __construct(
        private string $adminXml,
        private ?string $cacheFile = null,
    ) {}

    public function load(): SectionMap
    {
        if (! is_file($this->adminXml)) {
            return new SectionMap();
        }

        $stamp  = sprintf('%d:%d', (int) filemtime($this->adminXml), (int) filesize($this->adminXml));
        $cached = null === $this->cacheFile || ! is_file($this->cacheFile)
            ? null
            : SectionMapJson::decode((string) file_get_contents($this->cacheFile), $stamp);
        if (null !== $cached) {
            return $cached;
        }

        $map = SectionMap::fromAdminXml((string) file_get_contents($this->adminXml));
        $this->keep($stamp, $map);

        return $map;
    }

    /**
     * Writes the map to the cache file through a temporary file, so a request
     * never reads half of one.
     */
    private function keep(string $stamp, SectionMap $map): void
    {
        if (null === $this->cacheFile) {
            return;
        }

        $directory = dirname($this->cacheFile);
        if (! is_dir($directory) && ! mkdir($directory, permissions: 0o775, recursive: true)) {
            return;
        }

        $json      = SectionMapJson::encode($stamp, $map);
        $temporary = sprintf('%s.%s.tmp', $this->cacheFile, uniqid());
        if (null !== $json && false !== file_put_contents($temporary, $json)) {
            rename($temporary, $this->cacheFile);
        }
    }
}
