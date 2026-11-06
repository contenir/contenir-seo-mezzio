<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\TestAsset;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Table;
use Contenir\Resource\Core\Entity\AbstractResourceEntity;

/**
 * A site's resource with a JSON "seo" column for a record's own overrides.
 */
#[Table('resource')]
final class ProjectEntity extends AbstractResourceEntity
{
    #[Column]
    public ?string $seo = null;

    #[Column]
    public ?string $section = null;
}
