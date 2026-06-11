<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Limites d’envoi médias admin (Uppy) — partagées entre l’écran médias et l’import liste posts.
 */
final class AdminMediaUploadLimits
{
    /** @var list<string> */
    public const array ALL_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'application/pdf',
        'video/mp4',
    ];

    /** @var list<string> */
    public const array IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    public static function effectiveMaxBytes(string $envSpec): int
    {
        $parsed = self::parsePhpIniSize(trim($envSpec));
        $iniUpload = self::parsePhpIniSize((string) ini_get('upload_max_filesize'));
        $iniPost = self::parsePhpIniSize((string) ini_get('post_max_size'));
        $candidates = array_filter([$parsed, $iniUpload, $iniPost], static fn (int $v): bool => $v > 0);
        if ($candidates === []) {
            return 10 * 1024 * 1024;
        }

        return min($candidates);
    }

    private static function parsePhpIniSize(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        if (preg_match('/^(\d+)$/', $value, $m)) {
            return (int) $m[1];
        }

        if (!preg_match('/^(\d+)([KMG]?)$/i', $value, $m)) {
            return 0;
        }

        $n = (int) $m[1];
        $unit = strtoupper($m[2] ?? '');

        return match ($unit) {
            'G' => $n * 1024 * 1024 * 1024,
            'M' => $n * 1024 * 1024,
            'K' => $n * 1024,
            default => $n,
        };
    }
}
