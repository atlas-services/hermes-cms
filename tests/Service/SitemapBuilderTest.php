<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DataFixtures\PostFixtures;
use App\Service\SitemapBuilder;
use App\Tests\Base\BaseKernelTestCase;

final class SitemapBuilderTest extends BaseKernelTestCase
{
    protected function loadFixtures(): array
    {
        return [new PostFixtures()];
    }

    public function testBuildReturnsXmlAndHtmlGroupedByRoot(): void
    {
        /** @var SitemapBuilder $builder */
        $builder = static::getContainer()->get(SitemapBuilder::class);

        $result = $builder->build('fr', 'https://example.test');

        $this->assertArrayHasKey('xml', $result);
        $this->assertArrayHasKey('html', $result);
        $this->assertNotEmpty($result['xml']);
        $this->assertNotEmpty($result['html']);

        $first = $result['xml'][0];
        $this->assertStringStartsWith('https://example.test', $first['loc']);
        $this->assertStringContainsString('/fr', $first['loc']);
        $this->assertArrayHasKey('lastmod', $first);
        $this->assertSame('weekly', $first['changefreq']);
    }
}
