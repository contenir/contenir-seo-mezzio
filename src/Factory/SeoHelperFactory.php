<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Factory;

use Contenir\Seo\Mezzio\SeoHead;
use Contenir\Seo\Mezzio\View\SeoHelper;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Builds the seo() view helper. laminas-view hands a helper factory the
 * application's container, or its own plugin manager, which gives the
 * application's services.
 *
 * @api
 */
final class SeoHelperFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): SeoHelper
    {
        return new SeoHelper(Services::get($container, SeoHead::class, SeoHead::class));
    }
}
