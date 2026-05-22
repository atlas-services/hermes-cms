<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Form\ConfigType;
use App\Twig\ChevronExtension;
use PHPUnit\Framework\TestCase;

final class ChevronExtensionTest extends TestCase
{
    private ChevronExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new ChevronExtension();
    }

    public function testDefaults(): void
    {
        $style = $this->extension->slotStyle([]);

        self::assertStringContainsString('top: 95%', $style);
        self::assertStringContainsString('right: 5%', $style);
        self::assertStringContainsString('translate(0, -100%)', $style);
    }

    public function testVerticalLowBand(): void
    {
        $style = $this->extension->slotStyle(['chevron_position' => '3']);

        self::assertStringContainsString('top: 3%', $style);
        self::assertStringContainsString('translate(0, 0)', $style);
    }

    public function testVerticalHighBand(): void
    {
        $style = $this->extension->slotStyle(['chevron_position' => '98']);

        self::assertStringContainsString('top: 98%', $style);
        self::assertStringContainsString('translate(0, -100%)', $style);
    }

    public function testHorizontalPercent(): void
    {
        $style = $this->extension->slotStyle(['chevron_right' => '97%']);

        self::assertStringContainsString('right: 97%', $style);
    }

    public function testLegacyLabelsMapToPercent(): void
    {
        $style = $this->extension->slotStyle([
            'chevron_position' => 'bottom',
            'chevron_right' => 'left',
        ]);

        self::assertStringContainsString('top: 95%', $style);
        self::assertStringContainsString('right: 95%', $style);
    }

    public function testOutOfRangeClampsToNearestExtreme(): void
    {
        $style = $this->extension->slotStyle(['chevron_position' => '50']);

        self::assertStringContainsString('top: 95%', $style);
    }

    public function testConfigTypePercentChoices(): void
    {
        self::assertCount(11, ConfigType::CHEVRON_PERCENT);
        self::assertSame('1', ConfigType::CHEVRON_PERCENT['1 %']);
        self::assertSame('100', ConfigType::CHEVRON_PERCENT['100 %']);
    }
}
