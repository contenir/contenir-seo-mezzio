<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Factory;

use Contenir\Seo\JsonLd\Graph;
use Contenir\Seo\Mezzio\View\JsonLdHelper;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Builds the jsonLd() view helper over the request's Graph, the one SeoHead
 * adds the page's structured data to.
 *
 * @api
 */
final class JsonLdHelperFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): JsonLdHelper
    {
        return new JsonLdHelper(Services::get($container, Graph::class, Graph::class));
    }
}
