<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\TestAsset;

use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Mapping\Id;
use Contenir\Db\Model\Mapping\Table;

/**
 * A site's asset, as its entity maps the asset table.
 */
#[Table('asset')]
final class AssetEntity
{
    #[Id(generated: true)]
    #[Column('asset_id')]
    public ?int $assetId = null;

    #[Column]
    public ?string $type = null;

    #[Column]
    public ?string $path = null;

    #[Column]
    public ?string $active = null;
}
