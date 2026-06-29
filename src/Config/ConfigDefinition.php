<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Décrit le type d'édition et de normalisation d'une config Hermes.
 *
 * @param array<string, mixed> $choices
 */
final readonly class ConfigDefinition
{
    public function __construct(
        public string $code,
        public ConfigValueType $type,
        public array $choices = [],
        public bool $statefulBoolean = false,
        public bool $supportsTransparent = false,
    ) {
    }

    /**
     * @param array<string, mixed> $choices
     */
    public static function choice(string $code, array $choices): self
    {
        return new self($code, ConfigValueType::Choice, $choices);
    }

    public static function boolean(string $code, bool $stateful = false): self
    {
        return new self(
            code: $code,
            type: ConfigValueType::Boolean,
            choices: ['actif' => '1', 'inactif' => '0'],
            statefulBoolean: $stateful,
        );
    }

    public static function color(string $code): self
    {
        return new self($code, ConfigValueType::Color, supportsTransparent: true);
    }

    public static function text(string $code): self
    {
        return new self($code, ConfigValueType::Text);
    }

    public static function width(string $code): self
    {
        return new self($code, ConfigValueType::Width);
    }

    public static function fontFamily(string $code): self
    {
        return new self($code, ConfigValueType::FontFamily);
    }
}
