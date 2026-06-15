<?php

declare(strict_types=1);

namespace App\Enum;

/** Effets GSAP image-reveal (folio dynamique) — alignés sur assets/utils/gsap_image_reveal.js */
final class GsapImageRevealEffect
{
    public const AUCUN = 'aucun';

    public const HORIZONTAL_ALT = 'horizontal-alt';

    public const HORIZONTAL_LEFT = 'horizontal-left';

    public const HORIZONTAL_RIGHT = 'horizontal-right';

    public const VERTICAL_ALT = 'vertical-alt';

    public const VERTICAL_UP = 'vertical-up';

    public const VERTICAL_DOWN = 'vertical-down';

    public const FADE = 'fade';

    public const SCALE = 'scale';

    public const BLUR = 'blur';

    public const ROTATE = 'rotate';

    public const FOLIO_DYNAMIQUE_CODE = 'folio2';

    public const DEFAULT = self::HORIZONTAL_RIGHT;

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::AUCUN,
            self::HORIZONTAL_ALT,
            self::HORIZONTAL_LEFT,
            self::HORIZONTAL_RIGHT,
            self::VERTICAL_ALT,
            self::VERTICAL_UP,
            self::VERTICAL_DOWN,
            self::FADE,
            self::SCALE,
            self::BLUR,
            self::ROTATE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function choicesForAdmin(): array
    {
        return [
            self::AUCUN => 'Aucun',
            self::HORIZONTAL_ALT => 'horizontal-alt',
            self::HORIZONTAL_LEFT => 'horizontal-left',
            self::HORIZONTAL_RIGHT => 'horizontal-right',
            self::VERTICAL_ALT => 'vertical-alt',
            self::VERTICAL_UP => 'vertical-up',
            self::VERTICAL_DOWN => 'vertical-down',
            self::FADE => 'fade',
            self::SCALE => 'scale',
            self::BLUR => 'blur',
            self::ROTATE => 'rotate',
        ];
    }

    public static function isValid(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return \in_array($value, self::values(), true);
    }

    /** Valeur effective pour le front (défaut horizontal-right si non renseigné). */
    public static function resolveForFront(?string $stored): ?string
    {
        $v = trim((string) ($stored ?? ''));
        if ($v === '' || $v === self::AUCUN) {
            return null;
        }

        return self::isValid($v) ? $v : self::DEFAULT;
    }

    /** Valeur affichée / persistée côté admin (défaut visible dans le select). */
    public static function resolveForAdmin(?string $stored): string
    {
        $v = trim((string) ($stored ?? ''));
        if ($v === '' || $v === self::AUCUN) {
            return $v === self::AUCUN ? self::AUCUN : self::DEFAULT;
        }

        return self::isValid($v) ? $v : self::DEFAULT;
    }
}
