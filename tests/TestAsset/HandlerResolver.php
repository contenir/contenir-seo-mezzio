<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\TestAsset;

use Contenir\Preview\PreviewRecord;
use Contenir\Preview\Resolver\PreviewResolverInterface;
use Contenir\Preview\Resolver\PreviewTarget;
use Override;

/**
 * Shows every record at one handler, without routing, as a workflow page's preview is shown
 */
final readonly class HandlerResolver implements PreviewResolverInterface
{
    #[Override]
    public function resolve(PreviewRecord $record): PreviewTarget
    {
        return PreviewTarget::handler('page', $record->keys);
    }
}
