<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Sitemap;

use Contenir\Db\Model\Exception\ExceptionInterface as DbModelException;
use Contenir\Resource\Core\Entity\AbstractResourceEntity;
use Contenir\Resource\Core\Metadata\PageMetadataBuilder;
use Contenir\Resource\Core\ResourceManagerInterface;
use Contenir\Seo\Sitemap\Page;

use function is_array;
use function is_int;
use function is_string;

/**
 * A sitemap page's image: the share image the site gives the page's record,
 * as its own head does, through its page metadata builder. The record is the
 * one the page's workflow route carries, as contenir-resource-mezzio's
 * ResourceMiddleware reads it; a route without one, or a record that isn't
 * published, has no image.
 *
 * @internal
 */
final readonly class PageImages
{
    /** The route default holding the record's keys, as contenir-resource-mezzio's ResourceMiddleware names it. */
    public const string ROUTE_PARAMETER = 'id';

    /**
     * @param string $origin The site's address, such as "https://www.example.com", which a share image
     *                       given as a path on the site is at
     */
    public function __construct(
        private ResourceManagerInterface $resources,
        private PageMetadataBuilder $metadata,
        private string $origin,
    ) {}

    /**
     * $page with its record's share image, or as it is without one.
     *
     * @param mixed $route The page's workflow route, as the strategy gives it
     *
     * @throws DbModelException When the record cannot be read.
     */
    public function add(Page $page, mixed $route): Page
    {
        /** @var mixed $keys */
        $keys = is_array($route) ? $route['options']['defaults'][self::ROUTE_PARAMETER] ?? null : null;
        /** @var mixed $id */
        $id       = is_array($keys) ? $keys[AbstractResourceEntity::PRIMARY_KEY] ?? null : null;
        $resource = is_int($id) || is_string($id) ? $this->resources->findActive($id) : null;
        $image    = null === $resource ? null : $this->metadata->build($resource, $this->origin)->image;

        return (
            null === $image
                ? $page
                : new Page($page->url, $page->modified, [$image], $page->robots, $page->workflow)
        );
    }
}
