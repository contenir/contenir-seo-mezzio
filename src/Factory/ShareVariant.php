<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Factory;

use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Contenir\Storage\Variant;
use Contenir\Storage\VariantFit;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * The storage variant a site's share images are made as: one it declares under the
 * share_variant option's name, cropped to a fixed size, since only that gives every
 * image the same shape.
 *
 * @internal
 */
final class ShareVariant
{
    /**
     * @throws ContainerExceptionInterface When a service can't be built.
     */
    public static function of(ContainerInterface $container): ?Variant
    {
        $variant = Services::get($container, ProfileProviderService::class, ProfileProviderService::class)->variant(
            Options::fromContainer($container)->string('share_variant', 'share'),
        );

        return null !== $variant && $variant->width > 0 && $variant->height > 0 && VariantFit::Cover === $variant->fit
            ? $variant
            : null;
    }
}
