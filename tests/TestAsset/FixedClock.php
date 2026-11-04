<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\TestAsset;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

final readonly class FixedClock implements ClockInterface
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('@1792236000');
    }
}
