<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Section;
use App\Service\BackgroundColorResolver;
use Twig\Attribute\AsTwigFunction;

final class BackgroundColorExtension
{
    public function __construct(
        private readonly BackgroundColorResolver $backgroundColorResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $configs
     */
    #[AsTwigFunction('hermes_site_bgcolor')]
    public function siteBgcolor(array $configs): string
    {
        return $this->backgroundColorResolver->resolveSiteBackground($configs);
    }

    /**
     * @param array<string, mixed> $configs
     */
    #[AsTwigFunction('hermes_section_bgcolor')]
    public function sectionBgcolor(Section $section, array $configs): string
    {
        return $this->backgroundColorResolver->resolveSectionBackground($section, $configs);
    }

    /**
     * @param array<string, mixed> $configs
     */
    #[AsTwigFunction('hermes_section_text_color')]
    public function sectionTextColor(Section $section, array $configs): string
    {
        return $this->backgroundColorResolver->resolveSectionTextColor($section, $configs);
    }
}
