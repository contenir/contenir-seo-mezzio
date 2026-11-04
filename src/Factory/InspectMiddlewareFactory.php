<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Factory;

use Contenir\Preview\Mezzio\TargetMatcher;
use Contenir\Preview\PreviewEntry;
use Contenir\Seo\Mezzio\Exception\InvalidServiceException;
use Contenir\Seo\Mezzio\Inspect\InspectMiddleware;
use Mezzio\Router\RouterInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class InspectMiddlewareFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws InvalidServiceException When a service has another type.
     */
    public function __invoke(ContainerInterface $container): InspectMiddleware
    {
        return new InspectMiddleware(
            Services::get($container, PreviewEntry::class, PreviewEntry::class),
            new TargetMatcher(Services::get($container, RouterInterface::class, RouterInterface::class)),
            Services::get($container, ResponseFactoryInterface::class, ResponseFactoryInterface::class),
            Services::get($container, StreamFactoryInterface::class, StreamFactoryInterface::class),
        );
    }
}
