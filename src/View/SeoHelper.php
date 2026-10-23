<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\View;

use Contenir\Seo\Mezzio\SeoHead;

/**
 * View helper "seo" for laminas-view templates: $this->seo()->render(...)
 * in the layout's <head>.
 *
 * @api
 */
final readonly class SeoHelper
{
    public function __construct(
        private SeoHead $head,
    ) {}

    public function __invoke(): SeoHead
    {
        return $this->head;
    }
}
