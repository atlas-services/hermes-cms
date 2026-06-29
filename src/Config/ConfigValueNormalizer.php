<?php

declare(strict_types=1);

namespace App\Config;

final class ConfigValueNormalizer
{
    public function normalizeForStorage(ConfigDefinition $definition, mixed $value): ?string
    {
        return match ($definition->type) {
            ConfigValueType::Boolean => $this->toBool($value) ? '1' : '0',
            default => $value === null ? null : (string) $value,
        };
    }

    public function toBool(mixed $value): bool
    {
        if ($value === true) {
            return true;
        }

        if ($value === false || $value === null) {
            return false;
        }

        return \in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes', 'actif'], true);
    }
}
