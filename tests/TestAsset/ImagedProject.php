<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\TestAsset;

use Contenir\Resource\Core\Entity\AbstractResourceEntity;
use Override;

/**
 * A project whose share image is the one it is given, as a site's entity getter gives one
 */
final class ImagedProject extends AbstractResourceEntity
{
    public ?string $image = null;

    #[Override]
    public function getMetaImage(): ?string
    {
        return $this->image;
    }
}
