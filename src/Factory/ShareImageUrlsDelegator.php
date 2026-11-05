<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Factory;

use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Resource\Core\Metadata\ImageUrlResolverInterface;
use Contenir\Seo\Mezzio\Share\ShareImageUrls;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Wraps the site's image URLs with its share variant, when its storage config
 * declares one. A site that doesn't keeps its original images.
 *
 * @internal
 */
final class ShareImageUrlsDelegator
{
    /**
     * @param callable(): ImageUrlResolverInterface $callback
     *
     * @throws ContainerExceptionInterface When a service can't be built.
     *
     * @mago-expect analysis:unused-parameter The container passes every delegator the service's name.
     */
    public function __invoke(ContainerInterface $container, string $name, callable $callback): ImageUrlResolverInterface
    {
        $inner   = $callback();
        $variant = ShareVariant::of($container);

        return null === $variant
            ? $inner
            : new ShareImageUrls(
                $inner,
                Services::get($container, AssetUrlBuilder::class, AssetUrlBuilder::class),
                $variant->name,
            );
    }
}
