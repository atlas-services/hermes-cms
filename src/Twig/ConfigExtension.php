<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\ConfigGlobalsProvider;
use App\Service\FrontMenuService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class ConfigExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ConfigGlobalsProvider $configGlobalsProvider,
        private readonly FrontMenuService $frontMenuService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getGlobals(): array
    {
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';

        return [
            'configs' => $this->configGlobalsProvider->getConfigs(),
            'sectionsForTopbar' => $this->frontMenuService->getVisibleTopbarSections($locale),
            'sectionsForFooter' => $this->frontMenuService->getVisibleFooterSections($locale),
        ];
    }
}
