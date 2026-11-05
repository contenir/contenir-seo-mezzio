<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\TestAsset;

use Contenir\Resource\Core\Entity\AbstractResourceEntity;
use stdClass;

/**
 * A project with the kinds of relation a site's entity gives one: to many, and to one
 */
final class LinkedProject extends AbstractResourceEntity
{
    /** @var iterable<mixed> */
    public iterable $assets = [];

    public ?stdClass $hero = null;

    /** Set by nothing, as a relation that hasn't been loaded isn't. */
    public stdClass $unset;
}
