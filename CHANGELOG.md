# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.7.0] - 2026-11-05

### Added

- `Inspect\InspectMiddleware` answers `POST /api/seo/inspect` with what search engines would see of a record's
  page: its status, title, description, share image and alt text, canonical, robots and JSON-LD nodes. The CMS
  opens it with a signed preview token, as it does a preview, and the page is rendered as the preview would
  render it. Pipe it after `PreviewMiddleware`.

### Changed

- Requires contenir/contenir-preview `^0.3` and contenir/contenir-preview-mezzio `^0.1`.

## [0.6.0] - 2026-11-05

### Added

- `SeoHead::renderPage()`: the head for a page from its `PageMetadata`, with its own title, whether it writes its
  own description and share image, and its main JSON-LD node. `SeoHead::mainNode()` makes that node of the schema
  type the site's section map gives the page, or an Article when its og:type is article.
- The section map is kept in `contenir_seo.section_map_cache`, `data/cache/contenir-seo-section-map.cache` by
  default, and rebuilt only when the admin.xml changes. `false`, or a cache folder that doesn't exist, keeps no
  cache and reads the admin.xml on every request.

### Changed

- Requires contenir/contenir-seo `^0.6`, whose main JSON-LD node takes any schema type.

## [0.5.0] - 2026-11-04

### Added

- A record's own share title, description, image and alt text, from its advanced column, reach the page as
  contenir-resource's `PageOverrides`, which win over the resource's getters.

### Changed

- Requires contenir/contenir-seo `^0.5` and contenir/contenir-resource `^2.0.0-RC6`.

## [0.4.0] - 2026-11-04

### Added

- `Sections\SectionPageDefaults`: contenir-resource's `PageDefaultsInterface` from the `<seo type="…">` blocks in the
  site's admin.xml. A resource entity's type picks its block, and the block makes its page of the resource's own
  columns, read by name through contenir-db-model's `RowValues::of()`: it fills what the resource's getters leave
  empty, and gives the robots directive, canonical address and schema type. The factory reads the file at
  `contenir_seo.admin_xml`, `config/admin.xml` by default; the config provider offers it to the page metadata
  builder. A site without the file, or a resource whose type no block maps, gets no defaults.

### Changed

- Requires contenir/contenir-seo `^0.4`, contenir/contenir-resource `^2.0.0-RC5` and contenir/contenir-db-model
  `^2.0.0-rc6`, with `minimum-stability: dev` preferring stable, as contenir-resource does.

## [0.3.0] - 2026-10-30

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
