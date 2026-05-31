<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DataFixtures\PostFixtures;
use App\Service\AppLocaleService;
use App\Tests\Base\BaseKernelTestCase;

final class AppLocaleServiceTest extends BaseKernelTestCase
{
    private AppLocaleService $appLocaleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appLocaleService = static::getContainer()->get(AppLocaleService::class);
    }

    protected function loadFixtures(): array
    {
        return [new PostFixtures()];
    }

    public function testDefaultLocaleIsFrWithFormattedLabel(): void
    {
        self::assertSame('fr', $this->appLocaleService->getDefaultLocale());
        self::assertSame('France (FR)', $this->appLocaleService->formatLabel('fr'));
    }

    public function testContentLocalesIncludeFrFromFixtures(): void
    {
        $locales = $this->appLocaleService->getContentLocales();
        self::assertContains('fr', $locales);
        self::assertSame('fr', $locales[0]);
    }

    public function testAvailableTargetLocalesExcludeExistingContentLocales(): void
    {
        $choices = $this->appLocaleService->getAvailableTargetLocaleChoices('fr');
        self::assertNotContains('fr', $choices);
        self::assertContains('de', $choices);
    }
}
