<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio;

use function array_filter;
use function array_values;
use function in_array;
use function is_array;
use function is_file;
use function is_string;

/**
 * The add-ons a site has enabled, read as the CMS reads them: from
 * extensions.local.php when it lists any, otherwise from
 * extensions.global.php. The local list always replaces the global one,
 * which Laminas's own config merge (which joins lists) would not do.
 *
 * @api
 */
final readonly class EnabledAddOns
{
    /**
     * @param list<string> $handles
     */
    public function __construct(
        public array $handles = [],
    ) {}

    /**
     * The enabled add-ons from the two config files, as each returns them
     * (['extensions' => ['enabled' => [...]]]).
     *
     * @param array<array-key, mixed> $global
     * @param array<array-key, mixed> $local
     */
    public static function fromConfigs(array $global, array $local): self
    {
        return new self(self::listed($local) ?? self::listed($global) ?? []);
    }

    /**
     * The enabled add-ons from extensions.global.php and extensions.local.php
     * in $directory, either of which may be missing.
     */
    public static function fromDirectory(string $directory): self
    {
        return self::fromConfigs(
            self::read("{$directory}/extensions.global.php"),
            self::read("{$directory}/extensions.local.php"),
        );
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return list<string>|null Null when the file lists none
     *
     * @mago-expect analysis:mixed-assignment A config file is untyped; each level is checked here.
     */
    private static function listed(array $config): ?array
    {
        $extensions = $config['extensions'] ?? null;
        $enabled    = is_array($extensions) ? $extensions['enabled'] ?? null : null;
        if (! is_array($enabled)) {
            return null;
        }

        return array_values(array_filter($enabled, is_string(...)));
    }

    /**
     * @return array<array-key, mixed>
     *
     * @mago-expect analysis:mixed-assignment A config file returns whatever it returns; it is checked here.
     */
    private static function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $config = include $path;

        return is_array($config) ? $config : [];
    }

    public function has(string $handle): bool
    {
        return in_array($handle, $this->handles, strict: true);
    }
}
