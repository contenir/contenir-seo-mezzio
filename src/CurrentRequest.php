<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The request being answered, which SeoMiddleware records so that the head
 * service, called from a template, knows the host and path. It holds one
 * request at a time, so the container must serve one request, as under
 * PHP-FPM.
 *
 * @api
 */
final class CurrentRequest
{
    private ?ServerRequestInterface $request = null;

    public function get(): ?ServerRequestInterface
    {
        return $this->request;
    }

    public function set(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }
}
