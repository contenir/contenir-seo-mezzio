<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Unit;

use Contenir\Seo\Mezzio\EnabledAddOns;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class EnabledAddOnsTest extends TestCase
{
    /**
     * @return array<string, array{array<array-key, mixed>, array<array-key, mixed>, list<string>}>
     */
    public static function configs(): array
    {
        $global = ['extensions' => ['enabled' => ['tracking', 'seo']]];

        return [
            'global only'                  => [$global, [], ['tracking', 'seo']],
            'local replaces global'        => [$global, ['extensions' => ['enabled' => ['seo']]], ['seo']],
            'an empty local list'          => [$global, ['extensions' => ['enabled' => []]], []],
            'local with other settings'    => [$global, ['extensions' => ['other' => true]], ['tracking', 'seo']],
            'local extensions not a list'  => [$global, ['extensions' => 'tracking'], ['tracking', 'seo']],
            'local enabled not a list'     => [$global, ['extensions' => ['enabled' => 'seo']], ['tracking', 'seo']],
            'neither'                      => [[], [], []],
            'entries that are not handles' => [
                ['extensions' => ['enabled' => ['tracking', 3, null, 'seo']]],
                [],
                ['tracking', 'seo'],
            ],
        ];
    }

    #[Test]
    public function knowsAnAddOnThatIsNotEnabled(): void
    {
        static::assertFalse((new EnabledAddOns(['tracking']))->has('consent'));
    }

    #[Test]
    public function knowsAnEnabledAddOn(): void
    {
        static::assertTrue((new EnabledAddOns(['tracking']))->has('tracking'));
    }

    /**
     * @param array<array-key, mixed> $global
     * @param array<array-key, mixed> $local
     * @param list<string>            $handles
     */
    #[Test]
    #[DataProvider('configs')]
    public function readsTheListLocalOverGlobal(array $global, array $local, array $handles): void
    {
        static::assertSame($handles, EnabledAddOns::fromConfigs($global, $local)->handles);
    }

    #[Test]
    public function startsWithNothingEnabled(): void
    {
        static::assertSame([], (new EnabledAddOns())->handles);
    }
}
