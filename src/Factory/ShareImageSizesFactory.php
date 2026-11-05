<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Factory;

use Contenir\Resource\Core\Metadata\ImageSizeResolverInterface;
use Contenir\Resource\Core\Metadata\LocalImageSizeResolver;
use Contenir\Seo\Mezzio\Share\ShareImageSizes;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

use function is_array;
use function is_string;

/**
 * Sizes images as contenir-resource does from its image_root, and a share variant
 * from its declared size.
 *
 * @internal
 */
final class ShareImageSizesFactory
{
    /**
     * @throws ContainerExceptionInterface When a service can't be built.
     */
    public function __invoke(ContainerInterface $container): ImageSizeResolverInterface
    {
        /** @var mixed $config */
        $config = $container->get('config');
        /** @var mixed $resource */
        $resource = is_array($config) ? $config['contenir_resource'] ?? null : null;
        /** @var mixed $root */
        $root    = is_array($resource) ? $resource['image_root'] ?? null : null;
        $variant = ShareVariant::of($container);
        /** @var array{positive-int, positive-int}|null $size ShareVariant only gives a variant with both */
        $size = null === $variant ? null : [$variant->width, $variant->height];

        return new ShareImageSizes(is_string($root) ? new LocalImageSizeResolver($root) : null, $size);
    }
}
