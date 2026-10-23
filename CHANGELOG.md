# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
