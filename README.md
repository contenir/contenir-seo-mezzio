# contenir/contenir-seo-mezzio

[![Continuous Integration](https://github.com/contenir/contenir-seo-mezzio/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir/contenir-seo-mezzio/actions/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/contenir/contenir-seo-mezzio/graph/badge.svg)](https://codecov.io/gh/contenir/contenir-seo-mezzio)

The Mezzio adapter for [contenir/contenir-seo](https://github.com/contenir/contenir-seo). It serves a site's
`/sitemap.xml` and `/robots.txt`, gives templates the head's `<title>`, share defaults, verification tags and JSON-LD,
and hides staging and local copies from search engines.

The settings are set in the CMS, by the [SEO add-on](https://github.com/contenir-extension/seo). It is the Mezzio
counterpart of [contenir/contenir-seo-laminas-mvc](https://github.com/contenir/contenir-seo-laminas-mvc), and replaces
contenir/contenir-sitemap-mezzio.

## Requirements

- PHP 8.3, 8.4 or 8.5
- Mezzio with mezzio-router 3.17+ and [contenir/contenir-workflow-mezzio](https://github.com/contenir/contenir-workflow-mezzio) 2.1+
- PSR-17 response and stream factories in the container (laminas-diactoros's `ConfigProvider` registers both)

## Install

```bash
composer require contenir/contenir-seo-mezzio
```

laminas-component-installer adds `Contenir\Seo\Mezzio\ConfigProvider` to `config/config.php`. Then pipe the middleware
early in `config/pipeline.php`, before routing and before anything that answers requests itself, such as a page cache:

```php
$app->pipe(ErrorHandler::class);
$app->pipe(\Contenir\Seo\Mezzio\SeoMiddleware::class);
$app->pipe(PageCacheMiddleware::class);
$app->pipe(RouteMiddleware::class);
```

Piping it is what turns the package on. `SeoMiddleware`:

- answers `GET` and `HEAD` `/sitemap.xml`, `/sitemap-1.xml`, `/sitemap-2.xml`… and `/robots.txt` itself, so no routes
  are needed, whichever router the site uses. A disabled sitemap, or a numbered page the sitemap does not have, goes on
  down the pipeline to be not found;
- sends `X-Robots-Tag: noindex, nofollow` on every response of a copy of the site hidden from search engines;
- records the request, so the head knows the page's host and path.

## Settings

A Mezzio site's settings are its **top-level config**, where the CMS's `config/autoload/settings.global.php` merges,
with **`config['settings']` laid over it**, where the CMS writes `settings.local.php` (`SiteSettings::fromConfig()`).

The SEO add-on's settings are read only when the add-on is enabled in `config/autoload/extensions.global.php` or
`extensions.local.php` (a list in the local file replaces the global one, as the CMS reads it). Until then, only
`site.url`, `site.title` and `site.sitemap.enabled` are read, so a site keeps a plain sitemap and robots.txt and
nothing is hidden. The keys are listed in [contenir/contenir-seo's README](https://github.com/contenir/contenir-seo#settings).

Links are built on the site URL (`site.url`). Only without one do they fall back to the request's scheme and `Host`
header, which a client can spoof: set the site URL on every live site.

## The head

`SeoHead`, which the `seo()` view helper returns, builds the site's part of a page's `<head>`, and the `jsonLd()`
helper gathers the page's structured data, as `headMeta()` gathers its tags:

```php
<?php
use Contenir\Resource\Core\Metadata\PageMetadata;
use Contenir\Seo\Head\Article;

$article = PageMetadata::ARTICLE === $page?->type && null !== $page->title
    ? new Article($page->url, $page->title, $page->description, $page->image, $page->published, $page->updated)
    : null;
?>
<head>
    <meta charset="utf-8">
    <?= $this->seo()->render(
        title: $page?->title,
        hasDescription: null !== $page?->description,
        hasImage: null !== $page?->image,
        article: $article,
    ) ?>
    <?= $pageTags /* the page's own description, canonical, Open Graph and Twitter tags */ ?>
    <?= $this->jsonLd()->script($nonce) ?>
```

`render()` returns, escaped:

- the `<title>` from the site's template ("About us | Example"): don't write another;
- `og:site_name`, a robots meta tag when the page shouldn't be indexed, and on the home page the verification tags for
  Google, Bing, Pinterest and Meta;
- the site's default description, and its default share image with its alt text and size, for a page that has none of
  its own (say so with `hasDescription` and `hasImage`).

It adds the page's JSON-LD to `jsonLd()`'s graph: on the home page, the Organization and WebSite; elsewhere, an
Article for a page that is one (pass `article`) and the BreadcrumbList from the page's path. A template appends its
own nodes the same way, `$this->jsonLd()->append([...])`, and the layout writes them all once, in one script element
with `$nonce` when given. Write it after `render()` and anything else that appends.

`build()` returns the same as contenir-seo's `Head`, for a template that writes its own markup. With
[contenir-resource-mezzio](https://github.com/contenir/contenir-resource-mezzio), the page's own tags come from
`PageMetadata` (`ResourceAttribute::metadata($request)`) and `MetaTagRenderer::renderTags()`, which leaves the
`<title>` to this package.

The home page is the page at a home path, `/` unless `contenir_seo.home_paths` says otherwise. Template engines other
than laminas-view take `Contenir\Seo\Mezzio\SeoHead` from the container.

The head is per request, but the container's services are shared: the request is held by `CurrentRequest`, and the
structured data by the `Graph` service, so the container must serve one request, as under PHP-FPM, not a
long-running server.

## The sitemap

The sitemap lists every page of the contenir-workflow-mezzio navigation that has a route, including pages hidden from
the menu, with each page's `lastmod`, and then the pages of each `SitemapSourceInterface` service named in
`contenir_seo.sitemap_sources`, such as a site's articles, which are not in the page tree:

```php
final class Projects implements Contenir\Seo\Mezzio\SitemapSourceInterface
{
    public function pages(): iterable
    {
        foreach ($this->content->projects() as $project) {
            yield new Contenir\Seo\Sitemap\Page("/work/{$project->getSlug()}", $project->updated ?? $project->created);
        }
    }
}
```

Pages on another host, fragments and pages whose robots value is `noindex` are left out, each location is listed
once, and past 50,000 URLs the sitemap becomes an index of numbered pages.

## robots.txt

```text
# robots.txt for https://www.example.com/

User-agent: *
Allow: /

Disallow: /search

Sitemap: https://www.example.com/sitemap.xml
```

The middle block is the extra rules from the SEO add-on. A copy of the site hidden from search engines gets
`Disallow: /` instead, and no sitemap line.

## Configuration

Nothing is needed. To change the defaults:

```php
return [
    'contenir_seo' => [
        'extensions_config'  => 'config/autoload', // where extensions.global.php and .local.php are
        'public_path'        => 'public',          // the document root, to measure the default share image
        'home_paths'         => ['/'],             // the home page's paths
        'sitemap_navigation' => true,              // list the workflow navigation's pages
        'sitemap_sources'    => [],                // services adding pages to the sitemap
    ],
];
```

The workflow navigation comes from the strategy `workflow_manager.strategy` names, as contenir-workflow-mezzio reads it.

## Differences from the laminas-mvc adapter

| laminas-mvc | Mezzio |
|---|---|
| Routes and a controller for the sitemap and robots.txt | `SeoMiddleware` answers them, so no router syntax is involved |
| `seo()->head()` writes into `headTitle()` and `headMeta()` | `seo()->render()` returns the markup, and the template says whether the page has a description and image |
| The home page is the `home` route | The home page is at a home path (`/`) |
| Settings in `config['settings']` | Settings at the top level, with `config['settings']` laid over them |
| An MVC finish listener sends `X-Robots-Tag` | `SeoMiddleware` sends it |

## Development

The QA toolchain is [contenir/contenir-qa-tools](https://github.com/contenir/contenir-qa-tools).

```bash
composer check             # everything below
composer cs-check          # mago format --check && mago lint
composer static-analysis   # mago analyze
composer test              # unit suite
composer test-integration  # integration suite: a real container, laminas-view and files
composer mutation-test     # Infection
```

## Licence

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
