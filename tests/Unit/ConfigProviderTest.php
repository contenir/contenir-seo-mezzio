<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Unit;

use Contenir\Seo\Mezzio\ConfigProvider;
use Contenir\Seo\Mezzio\SeoMiddleware;
use Contenir\Seo\Mezzio\View\SeoHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_key_exists;

#[Group('unit')]
final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function itRegistersTheServicesAndTheSeoHelper(): void
    {
        $provider = new ConfigProvider();
        $config   = $provider();

        static::assertSame(
            [$provider->getDependencies(), $provider->getViewHelpers(), true, SeoHelper::class],
            [
                $config['dependencies'],
                $config['view_helpers'],
                array_key_exists(SeoMiddleware::class, $config['dependencies']['factories']),
                $config['view_helpers']['aliases']['seo'],
            ],
        );
    }
}
