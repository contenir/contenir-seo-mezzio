<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Unit\Factory;

use Contenir\Preview\PreviewConfig;
use Contenir\Preview\PreviewContext;
use Contenir\Preview\PreviewEntry;
use Contenir\Preview\Resolver\ResolverRegistry;
use Contenir\Preview\Token\TokenVerifier;
use Contenir\Seo\Mezzio\Factory\InspectMiddlewareFactory;
use Contenir\Seo\Mezzio\Inspect\InspectMiddleware;
use Contenir\Seo\Mezzio\Tests\TestAsset\ArrayContainer;
use Contenir\Seo\Mezzio\Tests\TestAsset\FixedClock;
use Contenir\SiteToken\Replay\InMemoryReplayGuard;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Mezzio\Router\RouterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Group('unit')]
final class InspectMiddlewareFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheEndpointFromTheSitesPreviewEntryAndFactories(): void
    {
        $clock    = new FixedClock();
        $shared   = 'a-secret-of-at-least-thirty-two-bytes!!';
        $verifier = new TokenVerifier(
            new PreviewConfig('https://admin.site.test', 'https://www.site.test', ['k1' => $shared], 'k1'),
            $clock,
            new InMemoryReplayGuard($clock),
        );

        $middleware = (new InspectMiddlewareFactory())(new ArrayContainer([
            PreviewEntry::class             => new PreviewEntry(
                $verifier,
                new ResolverRegistry([]),
                new PreviewContext(),
            ),
            RouterInterface::class          => $this->createStub(RouterInterface::class),
            ResponseFactoryInterface::class => new ResponseFactory(),
            StreamFactoryInterface::class   => new StreamFactory(),
        ]));
        $response = $middleware->process(
            new ServerRequest(
                uri: 'https://www.site.test' . InspectMiddleware::ENDPOINT,
                method: 'POST',
            ),
            $this->createStub(RequestHandlerInterface::class),
        );

        static::assertSame(
            [400, 'application/json'],
            [$response->getStatusCode(), $response->getHeaderLine('Content-Type')],
        );
    }
}
