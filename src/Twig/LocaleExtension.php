<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\AppLocaleService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class LocaleExtension extends AbstractExtension
{
    public function __construct(
        private readonly AppLocaleService $appLocaleService,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('locale_label', $this->formatLabel(...)),
        ];
    }

    public function formatLabel(string $locale): string
    {
        return $this->appLocaleService->formatLabel($locale);
    }
}
