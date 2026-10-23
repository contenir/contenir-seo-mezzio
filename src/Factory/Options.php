<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

use function is_array;
use function is_bool;
use function is_string;

/**
 * The application's config, and its contenir_seo options, each read with its
 * default when it is absent or of the wrong type.
 *
 * @internal
 */
final readonly class Options
{
    public const string KEY = 'contenir_seo';

    /**
     * @param array<array-key, mixed> $config
     * @param array<array-key, mixed> $options
     */
    private function __construct(
        public array $config,
        private array $options,
    ) {}

    /**
     * @throws ContainerExceptionInterface
     *
     * @mago-expect analysis:mixed-assignment The application's config is untyped; each part is checked here.
     */
    public static function fromContainer(ContainerInterface $container): self
    {
        $config  = $container->has('config') ? $container->get('config') : [];
        $config  = is_array($config) ? $config : [];
        $options = $config[self::KEY] ?? null;

        return new self($config, is_array($options) ? $options : []);
    }

    /**
     * @mago-expect analysis:mixed-assignment Options are untyped; the value is checked here.
     */
    public function flag(string $key, bool $default): bool
    {
        $value = $this->options[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }

    /**
     * @mago-expect analysis:mixed-assignment Options are untyped; the value is checked here.
     */
    public function string(string $key, string $default): string
    {
        $value = $this->options[$key] ?? null;

        return is_string($value) && '' !== $value ? $value : $default;
    }

    /**
     * The option's non-empty strings, or the default when it is not a list.
     *
     * @param list<string> $default
     *
     * @return list<string>
     *
     * @mago-expect analysis:mixed-assignment Options are untyped; each value is checked here.
     */
    public function strings(string $key, array $default): array
    {
        $value = $this->options[$key] ?? null;
        if (! is_array($value)) {
            return $default;
        }

        $strings = [];
        foreach ($value as $item) {
            if (! is_string($item) || '' === $item) {
                continue;
            }

            $strings[] = $item;
        }

        return $strings;
    }
}
