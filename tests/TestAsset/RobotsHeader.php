<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\TestAsset;

use Contenir\Seo\Mezzio\SeoMiddleware;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Sends a page with the X-Robots-Tag header given, if any, as the site's SEO middleware would
 */
final readonly class RobotsHeader implements MiddlewareInterface
{
    public function __construct(
        private ?string $robots = null,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        return null === $this->robots ? $response : $response->withHeader(SeoMiddleware::HEADER, $this->robots);
    }
}
