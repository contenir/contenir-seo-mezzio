<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\TestAsset;

use Contenir\Workflow\Strategy\ResourceStrategyInterface;
use Override;

/**
 * A workflow strategy with a fixed navigation and route list.
 */
final readonly class FixedStrategy implements ResourceStrategyInterface
{
    /**
     * @param array<array-key, mixed> $navigation
     * @param array<array-key, mixed> $routes
     */
    public function __construct(
        private array $navigation = [],
        private array $routes = [],
    ) {}

    #[Override]
    public function clearCache(): void {}

    #[Override]
    public function getNavigationConfig(): array
    {
        return $this->navigation;
    }

    #[Override]
    public function getRouteConfig(): array
    {
        return $this->routes;
    }
}
