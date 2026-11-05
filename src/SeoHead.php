<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio;

use Contenir\Resource\Core\Metadata\PageMetadata;
use Contenir\Seo\Head\Article;
use Contenir\Seo\Head\Head;
use Contenir\Seo\Head\HeadBuilder;
use Contenir\Seo\Head\HtmlRenderer;
use Contenir\Seo\Head\PageContext;
use Contenir\Seo\JsonLd\Graph;
use Contenir\Seo\Mezzio\Sitemap\WorkflowNavigationPages;
use Contenir\Seo\SeoConfig;
use Laminas\Cache\Exception\ExceptionInterface as CacheException;
use Mezzio\Router\Exception\RuntimeException as RouterException;
use Psr\Container\ContainerExceptionInterface;

use function in_array;

/**
 * The site's SEO for a page's <head>: the <title> from the site's template,
 * og:site_name, a robots meta tag, the verification tags on the home page,
 * and the default description and share image for a page without its own.
 * Render it after nothing else writes a <title>.
 *
 * The page's JSON-LD goes into the request's Graph, which the jsonLd() view
 * helper gives, for the layout to write once with whatever else the page
 * adds: the site's owner and the site on the home page; the article, when
 * the page is one, and the breadcrumb trail elsewhere.
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
        private Graph $graph = new Graph(),
        private ?WorkflowNavigationPages $navigation = null,
    ) {}

    /**
     * The page's main JSON-LD node from its metadata: of the schema type the
     * site's section map gives it, or an Article when its og:type is article,
     * and none when it has neither or no title.
     */
    public static function mainNode(PageMetadata $page): ?Article
    {
        $type = $page->schema ?? (PageMetadata::ARTICLE === $page->type ? 'Article' : null);
        if (null === $type || null === $page->title) {
            return null;
        }

        return new Article(
            $page->url,
            $page->title,
            $page->description,
            $page->image,
            $page->published,
            $page->updated,
            $type,
        );
    }

    /**
     * The head's parts for the page, for a template that writes its own markup.
     *
     * @param ?string  $title          The page's own title, before the site's template
     * @param bool     $hasDescription Whether the page writes its own description
     * @param bool     $hasImage       Whether the page writes its own share image
     * @param ?Article $article        The page as an article, when it is one
     */
    public function build(
        ?string $title = null,
        bool $hasDescription = false,
        bool $hasImage = false,
        ?Article $article = null,
    ): Head {
        $uri  = $this->request->get()?->getUri();
        $path = null === $uri ? '' : $uri->getPath();

        return (new HeadBuilder($this->config))->build(new PageContext(
            $title,
            null !== $uri && in_array($path, $this->homePaths, strict: true),
            null === $uri ? '' : $uri->getHost(),
            $hasDescription,
            $hasImage,
            $path,
            $article,
            $this->pages(),
        ));
    }

    /**
     * The request's structured data, which render() adds the page's to.
     */
    public function graph(): Graph
    {
        return $this->graph;
    }

    /**
     * The head's markup for the page, the <title> and the meta tags, escaped.
     * Its JSON-LD goes into the Graph, for the layout to write with jsonLd().
     *
     * @param ?string  $title          The page's own title, before the site's template
     * @param bool     $hasDescription Whether the page writes its own description
     * @param bool     $hasImage       Whether the page writes its own share image
     * @param ?Article $article        The page as an article, when it is one
     */
    public function render(
        ?string $title = null,
        bool $hasDescription = false,
        bool $hasImage = false,
        ?Article $article = null,
    ): string {
        $head = $this->build($title, $hasDescription, $hasImage, $article);
        $this->graph->append(...$head->jsonLd);

        return (new HtmlRenderer())->tags($head);
    }

    /**
     * The head's markup for a page with metadata, as render() writes it: the
     * page's own title, or $title for a page without metadata, whether it has
     * its own description and share image, and its main JSON-LD node.
     */
    public function renderPage(?PageMetadata $page, ?string $title = null): string
    {
        return $this->render(
            $page->title ?? $title,
            null !== $page?->description,
            null !== $page?->image,
            null === $page ? null : self::mainNode($page),
        );
    }

    /**
     * The site's pages' names by address, for the breadcrumb trail, or none where the workflow
     * navigation is not used or cannot be read, or the site's records switch is off: each step
     * is then named from its path.
     *
     * @return ?array<string, string>
     */
    private function pages(): ?array
    {
        try {
            return $this->config->records ? $this->navigation?->names() : null;
        } catch (CacheException|ContainerExceptionInterface|RouterException) {
            return null;
        }
    }
}
