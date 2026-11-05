<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Share;

use Contenir\Asset\Mezzio\Service\AssetUrlBuilder;
use Contenir\Resource\Core\Metadata\ImageUrlResolverInterface;
use Override;

/**
 * Points a page's share image at its share variant, so every page's image reaches
 * sharing sites the same shape, whichever field it came from. Anything that can't
 * have one, such as an image on another site, is left to the resolver it wraps.
 *
 * @internal
 */
final readonly class ShareImageUrls implements ImageUrlResolverInterface
{
    public function __construct(
        private ImageUrlResolverInterface $inner,
        private AssetUrlBuilder $urls,
        private string $variant,
    ) {}

    #[Override]
    public function resolve(string $image, string $origin): ?string
    {
        return $this->inner->resolve(
            ShareableImage::is($image) ? $this->urls->variantUrl($image, $this->variant) : $image,
            $origin,
        );
    }
}
