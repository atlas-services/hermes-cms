<?php

declare(strict_types=1);

namespace App\Controller\Admin\Trait;

use App\Entity\Menu;
use App\Service\AppLocaleService;
use Symfony\Component\HttpFoundation\Request;

trait AdminMenuLocaleFilterTrait
{
    protected AppLocaleService $appLocaleService;

    private function resolveMenuFilterLocale(Request $request): string
    {
        return $this->appLocaleService->resolveAdminFilterLocaleFromRequest($request);
    }

    /**
     * @return array{_locale: string, menu_locale: string}
     */
    private function menuIndexParams(Request $request, string $menuLocale): array
    {
        return [
            '_locale' => $request->getLocale(),
            'menu_locale' => $menuLocale,
        ];
    }

    /**
     * @return array{_locale: string, menu_locale: string, page?: int}
     */
    private function postIndexParams(Request $request, ?Menu $page = null, ?string $menuLocale = null): array
    {
        $params = [
            '_locale' => $request->getLocale(),
            'menu_locale' => $menuLocale ?? $page?->getLocale() ?? $this->resolveMenuFilterLocale($request),
        ];

        if ($page?->getId() !== null) {
            $params['page'] = $page->getId();
        }

        return $params;
    }
}
