<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\View;

use Contenir\Seo\JsonLd\Graph;

/**
 * View helper "jsonLd" for laminas-view templates: the request's structured
 * data, gathered as headMeta() gathers its tags. seo()->render() adds the
 * page's; a template appends its own; the layout writes it once:
 *
 *     <?php $this->jsonLd()->append(['@type' => 'CreativeWork', 'name' => $project->title]) ?>
 *     ...
 *     <?= $this->jsonLd()->script($nonce) ?>
 *
 * @api
 */
final readonly class JsonLdHelper
{
    public function __construct(
        private Graph $graph,
    ) {}

    public function __invoke(): Graph
    {
        return $this->graph;
    }
}
