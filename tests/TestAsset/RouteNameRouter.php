<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\TestAsset;

use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Mezzio\Router\RouterInterface;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A router whose route URIs are "/" and the route's name.
 */
final class RouteNameRouter implements RouterInterface
{
    #[Override]
    public function addRoute(Route $route): void {}

    #[Override]
    public function generateUri(string $name, array $substitutions = [], array $options = []): string
    {
        return "/{$name}";
    }

    #[Override]
    public function match(ServerRequestInterface $request): RouteResult
    {
        return RouteResult::fromRouteFailure(null);
    }
}
