<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Unit\Sections;

use Contenir\Seo\Mezzio\Sections\SectionMapJson;
use Contenir\Seo\Sections\SectionMap;
use Contenir\Seo\Sections\SeoBlock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SectionMapJsonTest extends TestCase
{
    #[Test]
    public function aMapReadsBackForTheStampItWasWrittenWith(): void
    {
        $map = new SectionMap([
            'project' => new SeoBlock('project', 'CreativeWork', ['title' => ['title']], ['description'], 'seo'),
            'news'    => new SeoBlock('news'),
        ]);

        static::assertEquals($map, SectionMapJson::decode((string) SectionMapJson::encode('1:2', $map), '1:2'));
    }

    #[Test]
    public function aMapWrittenForAnotherStampDoesNotReadBack(): void
    {
        static::assertNull(SectionMapJson::decode((string) SectionMapJson::encode('1:2', new SectionMap()), '3:4'));
    }
}
