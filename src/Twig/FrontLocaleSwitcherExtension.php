<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Menu;
use App\Service\FrontLocaleSwitcherService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FrontLocaleSwitcherExtension extends AbstractExtension
{
    public function __construct(
        private readonly FrontLocaleSwitcherService $localeSwitcherService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('front_locale_switcher', $this->buildLinks(...)),
        ];
    }

    /**
     * @return list<array{locale: string, label: string, url: string, active: bool}>
     */
    public function buildLinks(?Menu $currentMenu = null): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request?->getLocale() ?? 'fr';

        return $this->localeSwitcherService->buildLinks($currentMenu, $locale);
    }
}
