<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Section;
use App\Service\BackgroundColorResolver;
use PHPUnit\Framework\TestCase;

final class BackgroundColorResolverTest extends TestCase
{
    private BackgroundColorResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new BackgroundColorResolver();
    }

    public function testSiteBackgroundPrefersContentOverSite(): void
    {
        self::assertSame(
            '#ffffff',
            $this->resolver->resolveSiteBackground([
                'content_bgcolor' => '#ffffff',
                'bgcolor' => '#000000',
            ]),
        );
    }

    public function testSiteBackgroundSkipsTransparentContent(): void
    {
        self::assertSame(
            '#000000',
            $this->resolver->resolveSiteBackground([
                'content_bgcolor' => 'transparent',
                'bgcolor' => '#000000',
            ]),
        );
    }

    public function testSectionBackgroundPrefersSectionOverContentAndSite(): void
    {
        $section = (new Section())->setTemplateBgcolor('#ffcc00');

        self::assertSame(
            '#ffcc00',
            $this->resolver->resolveSectionBackground($section, [
                'content_bgcolor' => '#ffffff',
                'bgcolor' => '#000000',
            ]),
        );
    }

    public function testSectionBackgroundSkipsTransparentSection(): void
    {
        $section = (new Section())
            ->setTemplateBgcolor('#ffcc00')
            ->setTransparent(true);

        self::assertSame(
            '#ffffff',
            $this->resolver->resolveSectionBackground($section, [
                'content_bgcolor' => '#ffffff',
                'bgcolor' => '#000000',
            ]),
        );
    }

    public function testSectionBackgroundFallsBackWhenSectionColorUnset(): void
    {
        $section = new Section();

        self::assertSame(
            '#eeeeee',
            $this->resolver->resolveSectionBackground($section, [
                'content_bgcolor' => 'transparent',
                'bgcolor' => '#eeeeee',
            ]),
        );
    }

    public function testSectionTextColorPrefersSectionOverSite(): void
    {
        $section = (new Section())->setTemplateColor('#ff0000');

        self::assertSame(
            '#ff0000',
            $this->resolver->resolveSectionTextColor($section, ['text_color' => '#ffffff']),
        );
    }

    public function testSectionTextColorFallsBackToSiteTextColor(): void
    {
        $section = new Section();

        self::assertSame(
            '#abcdef',
            $this->resolver->resolveSectionTextColor($section, ['text_color' => '#abcdef']),
        );
    }
}
