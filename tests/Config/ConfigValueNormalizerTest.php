<?php

declare(strict_types=1);

namespace App\Tests\Config;

use App\Config\ConfigDefinition;
use App\Config\ConfigValueNormalizer;
use PHPUnit\Framework\TestCase;

final class ConfigValueNormalizerTest extends TestCase
{
    public function testNormalizesBooleanValuesForStorage(): void
    {
        $normalizer = new ConfigValueNormalizer();
        $definition = ConfigDefinition::boolean('affiche_search');

        self::assertSame('1', $normalizer->normalizeForStorage($definition, true));
        self::assertSame('1', $normalizer->normalizeForStorage($definition, 'yes'));
        self::assertSame('1', $normalizer->normalizeForStorage($definition, 'actif'));
        self::assertSame('0', $normalizer->normalizeForStorage($definition, false));
        self::assertSame('0', $normalizer->normalizeForStorage($definition, ''));
        self::assertSame('0', $normalizer->normalizeForStorage($definition, null));
    }

    public function testKeepsScalarValuesAsStringsForOtherTypes(): void
    {
        $normalizer = new ConfigValueNormalizer();

        self::assertSame('#ffffff', $normalizer->normalizeForStorage(ConfigDefinition::color('content_bgcolor'), '#ffffff'));
        self::assertSame('12', $normalizer->normalizeForStorage(ConfigDefinition::width('content_width'), 12));
        self::assertNull($normalizer->normalizeForStorage(ConfigDefinition::text('app_name'), null));
    }
}
