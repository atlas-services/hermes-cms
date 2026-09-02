<?php

declare(strict_types=1);

namespace App\Tests\Config;

use App\Config\ConfigDefinitionRegistry;
use App\Config\ConfigValueType;
use PHPUnit\Framework\TestCase;

final class ConfigDefinitionRegistryTest extends TestCase
{
    public function testIdentifiesExplicitBooleanConfigs(): void
    {
        $registry = new ConfigDefinitionRegistry();
        $definition = $registry->definitionFor('nav_left_open_on_load');

        self::assertSame(ConfigValueType::Boolean, $definition->type);
        self::assertTrue($definition->statefulBoolean);
        self::assertSame(['actif' => '1', 'inactif' => '0'], $definition->choices);
    }

    public function testKeepsKnownChoiceConfigsAsChoices(): void
    {
        $registry = new ConfigDefinitionRegistry();
        $definition = $registry->definitionFor('nav_bar');

        self::assertSame(ConfigValueType::Choice, $definition->type);
        self::assertSame(['base' => 'base', 'left' => 'left', 'full' => 'full'], $definition->choices);
    }

    public function testClassifiesColorAndWidthFallbacks(): void
    {
        $registry = new ConfigDefinitionRegistry();

        self::assertSame(ConfigValueType::Color, $registry->definitionFor('content_bgcolor')->type);
        self::assertSame(ConfigValueType::Color, $registry->definitionFor('booking_reservation_form')->type);
        self::assertSame(ConfigValueType::Width, $registry->definitionFor('content_width')->type);
        self::assertSame(ConfigValueType::FontFamily, $registry->definitionFor('title_font_family')->type);
        self::assertArrayHasKey('Cormorant Garamond', $registry->fontFamilyChoices());
        self::assertSame('\'Cormorant Garamond\', Georgia, serif', $registry->fontFamilyChoices()['Cormorant Garamond']);
        self::assertSame(ConfigValueType::Text, $registry->definitionFor('app_name')->type);
    }
}
