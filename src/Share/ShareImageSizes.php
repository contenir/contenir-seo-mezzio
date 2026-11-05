<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Share;

use Contenir\Resource\Core\Metadata\ImageSizeResolverInterface;
use Override;

/**
 * The size of a page's share image: its share variant's, which is known without
 * making it, or what the resolver it wraps finds for any other image.
 *
 * @internal
 */
final readonly class ShareImageSizes implements ImageSizeResolverInterface
{
    /**
     * @param array{positive-int, positive-int}|null $variant The share variant's width and height; null when the site has none
     */
    public function __construct(
        private ?ImageSizeResolverInterface $inner,
        private ?array $variant,
    ) {}

    #[Override]
    public function size(string $image): ?array
    {
        return null !== $this->variant && ShareableImage::is($image) ? $this->variant : $this->inner?->size($image);
    }
}
