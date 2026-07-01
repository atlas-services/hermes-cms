<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Section;

/**
 * Cascade des couleurs de fond front : section > contenu > site.
 * Un niveau « transparent » (ou vide) est ignoré.
 */
final class BackgroundColorResolver
{
    /**
     * @param array<string, mixed> $configs
     */
    public function resolveSiteBackground(array $configs): string
    {
        return $this->resolveFromCandidates([
            $configs['content_bgcolor'] ?? null,
            $configs['bgcolor'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $configs
     */
    public function resolveSectionBackground(Section $section, array $configs): string
    {
        $candidates = [];

        if (!$section->isTransparent()) {
            $candidates[] = $section->getRawTemplateBgcolor();
        }

        $candidates[] = $configs['content_bgcolor'] ?? null;
        $candidates[] = $configs['bgcolor'] ?? null;

        return $this->resolveFromCandidates($candidates);
    }

    /**
     * @param list<mixed> $candidates
     */
    private function resolveFromCandidates(array $candidates): string
    {
        foreach ($candidates as $color) {
            if ($this->isUsableColor($color)) {
                return trim((string) $color);
            }
        }

        return 'transparent';
    }

    private function isUsableColor(mixed $color): bool
    {
        if (!\is_string($color)) {
            return false;
        }

        $normalized = strtolower(trim($color));

        return $normalized !== '' && $normalized !== 'transparent';
    }
}
