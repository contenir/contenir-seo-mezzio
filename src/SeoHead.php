<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio;

use Contenir\Seo\Head\Head;
use Contenir\Seo\Head\HeadBuilder;
use Contenir\Seo\Head\HtmlRenderer;
use Contenir\Seo\Head\PageContext;
use Contenir\Seo\SeoConfig;
use JsonException;

use function in_array;

/**
 * The site's SEO for a page's <head>: the <title> from the site's template,
 * og:site_name, a robots meta tag, the verification tags and JSON-LD on the
 * home page, and the default description and share image for a page without
 * its own. Render it after nothing else writes a <title>.
 *
 * The page's host and path come from the request SeoMiddleware recorded; the
 * home page is the one at a home path ("/" by default).
 *
 * @api
 */
final readonly class SeoHead
{
    /**
     * @param list<string> $homePaths The paths of the home page
     */
    public function __construct(
        private SeoConfig $config,
        private CurrentRequest $request,
        private array $homePaths = ['/'],
    ) {}

    /**
     * The head's parts for the page, for a template that writes its own markup.
     *
     * @param ?string $title          The page's own title, before the site's template
     * @param bool    $hasDescription Whether the page writes its own description
     * @param bool    $hasImage       Whether the page writes its own share image
     */
    public function build(?string $title = null, bool $hasDescription = false, bool $hasImage = false): Head
    {
        $uri = $this->request->get()?->getUri();

        return (new HeadBuilder($this->config))->build(new PageContext(
            $title,
            null !== $uri && in_array($uri->getPath(), $this->homePaths, strict: true),
            null === $uri ? '' : $uri->getHost(),
            $hasDescription,
            $hasImage,
        ));
    }

    /**
     * The head's markup for the page: the <title>, the meta tags and, on the
     * home page, the JSON-LD script, all escaped.
     *
     * @param ?string $title          The page's own title, before the site's template
     * @param bool    $hasDescription Whether the page writes its own description
     * @param bool    $hasImage       Whether the page writes its own share image
     * @param ?string $nonce          A CSP nonce for the JSON-LD script
     *
     * @throws JsonException Never in practice; see contenir/contenir-seo's JsonLd.
     */
    public function render(
        ?string $title = null,
        bool $hasDescription = false,
        bool $hasImage = false,
        ?string $nonce = null,
    ): string {
        return (new HtmlRenderer())->render($this->build($title, $hasDescription, $hasImage), $nonce);
    }
}
