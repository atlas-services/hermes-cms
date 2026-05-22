<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFunction;

/**
 * Position fixe des chevrons front (configs : chevron_position, chevron_right en %).
 * Valeurs autorisées : 1–5 et 95–100 (pas de 1).
 */
final class ChevronExtension
{
    private const DEFAULT_VERTICAL = 95;
    private const DEFAULT_HORIZONTAL = 5;

    /**
     * @param array<string, mixed> $configs
     */
    #[AsTwigFunction('hermes_chevron_slot_style')]
    public function slotStyle(array $configs): string
    {
        $topPercent = $this->parsePercent($configs['chevron_position'] ?? null, self::DEFAULT_VERTICAL);
        $rightPercent = $this->parsePercent($configs['chevron_right'] ?? null, self::DEFAULT_HORIZONTAL);

        $styles = [
            'position: fixed',
            'z-index: 9998',
            sprintf('top: %d%%', $topPercent),
            sprintf('right: %d%%', $rightPercent),
            'left: auto',
        ];

        $translateY = $topPercent >= 95 ? '-100%' : '0';
        $styles[] = sprintf('transform: translate(0, %s)', $translateY);

        return implode('; ', $styles);
    }

    private function parsePercent(mixed $value, int $default): int
    {
        $n = $this->rawPercent($value);
        if ($n === null) {
            return $this->clampToAllowedPercent($default);
        }

        return $this->clampToAllowedPercent($n);
    }

    private function rawPercent(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $s = strtolower(trim((string) $value));
        if ($s === '' || $s === '~') {
            return null;
        }

        $s = rtrim($s, '%');

        if (is_numeric($s)) {
            return (int) round((float) $s);
        }

        return match ($s) {
            'top' => 5,
            'bottom' => 95,
            'right' => 5,
            'left' => 95,
            'middle' => 50,
            default => null,
        };
    }

    private function clampToAllowedPercent(int $n): int
    {
        if ($n >= 1 && $n <= 5) {
            return $n;
        }

        if ($n >= 95 && $n <= 100) {
            return $n;
        }

        return $n < 50 ? min(5, max(1, $n)) : max(95, min(100, $n));
    }
}
