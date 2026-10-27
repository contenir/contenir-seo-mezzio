<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\TestAsset;

use Mezzio\Router\Exception\RuntimeException;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Mezzio\Router\RouterInterface;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A router that can address no route, as one whose routes all need parameters.
 */
final readonly class UnroutableRouter implements RouterInterface
{
    #[Override]
    public function addRoute(Route $route): void {}

    /**
     * @throws RuntimeException Always.
     */
    #[Override]
    public function generateUri(string $name, array $substitutions = [], array $options = []): string
    {
        throw new RuntimeException("The route {$name} needs parameters.");
    }

    #[Override]
    public function match(ServerRequestInterface $request): RouteResult
    {
        return RouteResult::fromRouteFailure(null);
    }
}
