# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [0.11.0] - 2026-11-06

### Added

- The master switch for what a site's `<seo>` blocks drive, `SeoConfig::$records`, read from `extensions.seo.records`
  and, where the settings don't have it, from `contenir_seo.records`. It is off unless a site turns it on. While off,
  `PageDefaultsInterface` still gives a record's title and description (from the block's columns and the record's own
  advanced column), since where they come from is not gated, but gives no image, canonical address, robots or schema
  (so no block-driven schema), nor overrides of them; the breadcrumb trail is named from its path, and the sitemap
  lists no share images.

### Changed

- Requires `contenir/contenir-seo` ^0.8.

## [0.10.1] - 2026-11-05

### Added

- A `<seo>` block's role can read a linked record's column, as `<image>assets[type=hero].path, assets[type=thumbnail].path</image>`,
  or `hero.path` for a relation to one record. The relation goes by the entity's property, and the columns by their names.

## [0.10.0] - 2026-11-05

### Added

- A page's share image can be a share variant, so every page's image reaches sharing sites the same shape, whichever
  field it came from. A site turns it on by declaring a `share` variant with a fixed width and height and `fit: cover` in
  its storage config (1200×630 is what most sharing sites show), and names another with `share_variant`. A path on the
  site of a kind that can be cropped goes through it, and its size comes from the variant. A site without one keeps its
  original images, as does an image on another site. Requires contenir/contenir-asset-mezzio.

## [0.9.0] - 2026-11-05

### Added

- The sitemap lists each workflow page's share image: the one the site's own `PageMetadataBuilder` gives the page's
  head, from the record the page's route carries, so the sitemap and the page never disagree. It needs
  contenir-resource's `ResourceManagerInterface`, which every resource site has, and is switched off with
  `contenir_seo.sitemap_images: false`. A page without a published record, or whose record has no share image,
  lists none.

## [0.8.0] - 2026-11-05

### Added

- A page's breadcrumb trail names each step as the site's workflow navigation labels it, and leaves out a step that
  is no page of the site: Sector7g's projects at `/project/a-house` no longer link to a `/project` that isn't there.
  `WorkflowNavigationPages::names()` gives the routed pages' labels by address. With `sitemap_navigation` off, or a
  navigation that can't be read, steps are named from the path as before.

### Changed

- Requires contenir/contenir-seo `^0.7`.

## [0.7.1] - 2026-11-05

### Fixed

- `/api/seo/inspect` rendered a page the site dispatches without routing, as a workflow page's preview is, at the
  endpoint's own address, which its canonical, share URL and breadcrumbs then gave. The CMS now posts the record's
  public path as `path`, and the page is rendered there; a route or path the site resolves itself still wins.
- The page is rendered through `SeoMiddleware` once more at that path, so its head reads the record's path, and the
  answer gains `robotsHeader`: the X-Robots-Tag the page is sent with, such as an undiscoverable page's.

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
