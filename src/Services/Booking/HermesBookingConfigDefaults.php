<?php

declare(strict_types=1);

namespace App\Services\Booking;

use Symfony\Component\Yaml\Yaml;

/**
 * Charge config/hermes_booking_configs.yaml uniquement si le bundle booking est installé.
 */
final class HermesBookingConfigDefaults
{
    private const BUNDLE_CLASS = \AtlasServices\HermesBookingBundle\HermesBookingBundle::class;

    public static function isAvailable(): bool
    {
        return class_exists(self::BUNDLE_CLASS);
    }

    /**
     * @return array<string, array<string, array{summary?: string, value?: mixed, position?: int}>>
     */
    public static function load(): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $path = \dirname(__DIR__, 3).'/config/hermes_booking_configs.yaml';
        if (!\is_file($path)) {
            return [];
        }

        /** @var array<string, array<string, array{summary?: string, value?: mixed, position?: int}>> $parsed */
        $parsed = Yaml::parseFile($path);

        return $parsed;
    }
}
