<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Exception;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

use function get_debug_type;
use function sprintf;

/**
 * A service, or a contenir_seo option naming one, is not what it must be.
 *
 * @api
 */
final class InvalidServiceException extends RuntimeException implements ContainerExceptionInterface
{
    public static function forService(string $name, string $type, mixed $service): self
    {
        return new self(sprintf('Service "%s" must be a %s, %s given', $name, $type, get_debug_type($service)));
    }
}
