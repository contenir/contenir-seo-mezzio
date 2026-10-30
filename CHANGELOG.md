# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- The `jsonLd()` view helper: the request's `Graph` (contenir/contenir-seo's), which `seo()->render()`, other
  packages and the page's template append structured data to, and the layout writes once with `script($nonce)`.
- `seo()->render()` and `build()` take an `article`, contenir-seo's `Article`, for the page's Article JSON-LD; every
  page but the home page gets a BreadcrumbList from its path.

### Changed

- `render()` no longer writes the JSON-LD script, and no longer takes a `nonce`: it adds the nodes to the `Graph`, and
  the layout writes them with `$this->jsonLd()->script($nonce)`.
- contenir/contenir-seo `^0.3` is required.

## [0.2.0] - 2026-10-27

### Changed

- A workflow navigation page marked `visible: false` is undiscoverable: it keeps its address, but the sitemap leaves
  it out (its children stay in), and `SeoMiddleware` answers it with `X-Robots-Tag: noindex, follow`.
  contenir/contenir-resource-mezzio marks a resource so when its `visible` column is off, which the CMS labels
  Discoverable. A site that keeps the navigation out of its sitemap (`contenir_seo.sitemap_navigation: false`)
  marks no page so.

## [0.1.0] - 2026-10-23

### Added

- `SeoMiddleware`: `/sitemap.xml`, its numbered pages past 50,000 URLs and `/robots.txt` from the site's SEO
  settings, without routes; `X-Robots-Tag: noindex, nofollow` on every response of a copy of the site hidden from
  search engines.
- `SeoHead` and the `seo()` view helper: the `<title>` from the site's template, `og:site_name`, robots, verification
  tags, the default description and share image for a page without its own, and the home page's JSON-LD.
- The sitemap's pages from the contenir-workflow-mezzio navigation, hidden pages included, and from
  `SitemapSourceInterface` services named in `contenir_seo.sitemap_sources`.
- Settings read as a Mezzio site holds them: the top-level config with `config['settings']` laid over it, and the
  add-on's settings only while it is enabled in `extensions.global.php` or `extensions.local.php`.
