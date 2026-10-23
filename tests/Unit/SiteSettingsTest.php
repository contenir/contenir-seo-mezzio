<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Unit;

use Contenir\Seo\Mezzio\CurrentRequest;
use Contenir\Seo\Mezzio\Exception\InvalidServiceException;
use Contenir\Seo\Mezzio\SiteSettings;
use Contenir\Seo\SeoConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[Group('unit')]
final class SiteSettingsTest extends TestCase
{
    #[Test]
    public function aServiceOfTheWrongTypeIsNamed(): void
    {
        static::assertSame(
            'Service "seo" must be a Contenir\Seo\SeoConfig, stdClass given',
            InvalidServiceException::forService('seo', SeoConfig::class, new stdClass())->getMessage(),
        );
    }

    #[Test]
    public function localSettingsAreLaidOverTheTopLevelOnes(): void
    {
        $settings = SiteSettings::fromConfig([
            'site'     => ['url' => 'https://www.example.test', 'title' => 'Example'],
            'settings' => ['site' => ['url' => 'http://example.local']],
        ]);

        static::assertSame(['http://example.local', 'Example'], [$settings['site']['url'], $settings['site']['title']]);
    }

    #[Test]
    public function noRequestIsRecordedUntilOneIs(): void
    {
        static::assertNull((new CurrentRequest())->get());
    }

    #[Test]
    public function withoutLocalSettingsTheTopLevelOnesAreRead(): void
    {
        static::assertSame(
            ['site' => ['title' => 'Example'], 'settings' => 'x'],
            SiteSettings::fromConfig([
                'site'     => ['title' => 'Example'],
                'settings' => 'x',
            ]),
        );
    }
}
