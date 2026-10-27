<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Sitemap;

use Contenir\Seo\Mezzio\SitemapSourceInterface;
use Contenir\Seo\Sitemap\Page;
use Contenir\Workflow\Strategy\ResourceStrategyInterface;
use DateTimeInterface;
use Laminas\Cache\Exception\ExceptionInterface as CacheException;
use Mezzio\Router\Exception\RuntimeException as RouterException;
use Mezzio\Router\RouterInterface;
use Override;
use Psr\Container\ContainerExceptionInterface;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function date_create_immutable;
use function is_array;
use function is_string;

/**
 * Every page of the contenir-workflow-mezzio navigation that has a route of
 * its own and is discoverable, as sitemap pages, with each page's lastmod. A
 * page whose resource has no middleware (a folder) has no route, and is left
 * out; so is one marked `visible: false`, which keeps its address but is not
 * advertised. The children of either are listed as their own.
 *
 * The undiscoverable pages' addresses are given too, for SeoMiddleware to keep
 * them out of search results.
 *
 * @api
 */
final readonly class WorkflowNavigationPages implements SitemapSourceInterface
{
    public function __construct(
        private ResourceStrategyInterface $strategy,
        private RouterInterface $router,
    ) {}

    private static function modified(mixed $value): ?DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        $date = is_string($value) && '' !== $value ? date_create_immutable($value) : false;

        return false === $date ? null : $date;
    }

    /**
     * @return list<Page>
     *
     * @throws CacheException When the workflow cache cannot be read or written.
     * @throws ContainerExceptionInterface When a workflow cannot be created.
     * @throws RouterException When a page's route needs parameters to generate its URL.
     */
    #[Override]
    public function pages(): array
    {
        return array_values(array_map(
            static fn(RoutedPage $routed): Page => $routed->page,
            array_filter($this->routed(), static fn(RoutedPage $routed): bool => $routed->discoverable),
        ));
    }

    /**
     * The addresses of the routed pages marked `visible: false`.
     *
     * @return list<string>
     *
     * @throws CacheException When the workflow cache cannot be read or written.
     * @throws ContainerExceptionInterface When a workflow cannot be created.
     * @throws RouterException When a page's route needs parameters to generate its URL.
     */
    public function undiscoverable(): array
    {
        return array_values(array_map(
            static fn(RoutedPage $routed): string => $routed->page->url,
            array_filter($this->routed(), static fn(RoutedPage $routed): bool => ! $routed->discoverable),
        ));
    }

    /**
     * @param array<array-key, mixed>  $pages
     * @param array<array-key, mixed>  $routes
     * @param list<RoutedPage>  $found
     *
     * @return list<RoutedPage>
     *
     * @throws RouterException When a page's route needs parameters to generate its URL.
     *
     * @mago-expect analysis:mixed-assignment Navigation pages are untyped arrays; each value is checked here.
     */
    private function collect(array $pages, array $routes, array $found = []): array
    {
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }

            $route = $page['route'] ?? null;
            if (is_string($route) && array_key_exists($route, $routes)) {
                $found[] = new RoutedPage(
                    new Page($this->router->generateUri($route), self::modified($page['lastmod'] ?? null)),
                    false !== ($page['visible'] ?? true),
                );
            }

            $children = $page['pages'] ?? null;
            if (is_array($children)) {
                $found = $this->collect($children, $routes, $found);
            }
        }

        return $found;
    }

    /**
     * Every routed page, with whether it is discoverable.
     *
     * @return list<RoutedPage>
     *
     * @throws CacheException
     * @throws ContainerExceptionInterface
     * @throws RouterException
     */
    private function routed(): array
    {
        return $this->collect($this->strategy->getNavigationConfig(), $this->strategy->getRouteConfig());
    }
}
