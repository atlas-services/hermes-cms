<?php

declare(strict_types=1);

namespace App\Services\Booking;

use Doctrine\DBAL\Connection;
use Twig\Attribute\AsTwigFunction;

/**
 * Point unique pour savoir si le module réservation est utilisable.
 *
 * Activation = bundle Composer présent
 *              ET HERMES_BOOKING_ENABLED=1
 *              ET tables booking_* créées (migrations exécutées).
 */
final class HermesBookingStatus
{
    private const BUNDLE_CLASS = \AtlasServices\HermesBookingBundle\HermesBookingBundle::class;
    private const ENV_KEY = 'HERMES_BOOKING_ENABLED';

    /** @var list<string> */
    private const REQUIRED_TABLES = [
        'booking_calendar',
        'booking_blocked_date',
        'booking_reservation',
    ];

    private ?bool $schemaReady = null;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function isInstalled(): bool
    {
        return class_exists(self::BUNDLE_CLASS);
    }

    public function isEnabled(): bool
    {
        if (!$this->isInstalled()) {
            return false;
        }

        $raw = $this->resolveEnvValue(self::ENV_KEY);
        if ($raw === null || $raw === '') {
            return false;
        }

        return filter_var($raw, \FILTER_VALIDATE_BOOL);
    }

    private function resolveEnvValue(string $key): ?string
    {
        foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null] as $candidate) {
            if (\is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        $fromGetenv = getenv($key);
        if (\is_string($fromGetenv) && $fromGetenv !== '') {
            return $fromGetenv;
        }

        return $this->readFromEnvFiles($key);
    }

    public function hasDatabaseSchema(): bool
    {
        if ($this->schemaReady !== null) {
            return $this->schemaReady;
        }

        if (!$this->isInstalled()) {
            return $this->schemaReady = false;
        }

        try {
            $this->schemaReady = $this->connection
                ->createSchemaManager()
                ->tablesExist(self::REQUIRED_TABLES);
        } catch (\Throwable) {
            $this->schemaReady = false;
        }

        return $this->schemaReady;
    }

    #[AsTwigFunction('hermes_booking_active')]
    public function isActive(): bool
    {
        return $this->isInstalled() && $this->isEnabled() && $this->hasDatabaseSchema();
    }

    private function readFromEnvFiles(string $key): ?string
    {
        static $cached;
        if (\is_array($cached) && \array_key_exists($key, $cached)) {
            return $cached[$key];
        }

        $cached ??= [];
        $rootDir = \dirname(__DIR__, 3);

        foreach (['.env.local', '.env'] as $filename) {
            $path = $rootDir . '/' . $filename;
            if (!\is_file($path)) {
                continue;
            }

            $content = @\file_get_contents($path);
            if ($content === false) {
                continue;
            }

            foreach (\preg_split('/\R/', $content) as $line) {
                $line = \trim($line);
                if ($line === '' || \str_starts_with($line, '#')) {
                    continue;
                }

                // Match: KEY=value, KEY="value", export KEY=value, etc.
                if (\preg_match('/^(?:export\s+)?' . \preg_quote($key, '/') . '\s*=\s*(.+)\s*$/', $line, $m) !== 1) {
                    continue;
                }

                $value = \trim($m[1]);
                $value = \trim($value, '\'"');
                $cached[$key] = $value === '' ? null : $value;
                return $cached[$key];
            }
        }

        $cached[$key] = null;
        return null;
    }
}

