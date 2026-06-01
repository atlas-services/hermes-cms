<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Menu;
use App\Repository\MenuRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Liens de bascule de langue front (équivalent page par {@see Menu::getReferenceName()}).
 */
final class FrontLocaleSwitcherService
{
    public function __construct(
        private readonly MenuRepository $menuRepository,
        private readonly FrontMenuService $frontMenuService,
        private readonly MenuContactProvisioner $contactProvisioner,
        private readonly MenuReferenceNameResolver $referenceNameResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AppLocaleService $appLocaleService,
    ) {
    }

    /**
     * @return list<array{locale: string, label: string, url: string, active: bool}>
     */
    public function buildLinks(?Menu $currentMenu, string $currentLocale): array
    {
        $availableLocales = $this->findLocalesWithDisplayableContent();
        if (\count($availableLocales) <= 1) {
            return [];
        }

        $links = [];
        foreach ($availableLocales as $locale) {
            $targetMenu = $this->resolveTargetMenu($currentMenu, $locale);
            if ($targetMenu === null || !$this->menuHasDisplayableContent($targetMenu, $locale)) {
                continue;
            }

            $links[] = [
                'locale' => $locale,
                'label' => $this->appLocaleService->formatFrontLabel($locale),
                'url' => $this->buildMenuUrl($targetMenu, $locale),
                'active' => $locale === $currentLocale,
            ];
        }

        return $links;
    }

    /**
     * @return list<string>
     */
    private function findLocalesWithDisplayableContent(): array
    {
        $locales = [];
        foreach ($this->appLocaleService->getContentLocales() as $locale) {
            if ($this->findFirstPageWithDisplayableContent($locale) !== null) {
                $locales[] = $locale;
            }
        }

        return $locales;
    }

    private function resolveTargetMenu(?Menu $currentMenu, string $targetLocale): ?Menu
    {
        if ($currentMenu === null) {
            return $this->findFirstPageWithDisplayableContent($targetLocale);
        }

        if ($this->isContactMenu($currentMenu)) {
            $contact = $this->frontMenuService->findContactPage($targetLocale);

            return $contact ?? $this->findFirstPageWithDisplayableContent($targetLocale);
        }

        $referenceName = $this->referenceNameResolver->resolve($currentMenu);
        $equivalent = $this->findMenuByResolvedReference($targetLocale, $referenceName);
        if ($this->isValidTargetPage($equivalent, $targetLocale)) {
            return $equivalent;
        }

        $slugParts = array_values(array_filter(explode('/', $this->buildSlugPath($currentMenu))));
        if ($slugParts !== []) {
            $bySlug = $this->menuRepository->findOneBySlugPath($targetLocale, $slugParts);
            if ($this->isValidTargetPage($bySlug, $targetLocale)) {
                return $bySlug;
            }
        }

        return $this->findFirstPageWithDisplayableContent($targetLocale);
    }

    private function findFirstPageWithDisplayableContent(string $locale): ?Menu
    {
        foreach ($this->menuRepository->findRootsByLocale($locale) as $root) {
            if (!$root->isActive()) {
                continue;
            }
            $page = $this->findFirstDisplayablePageInTree($root, $locale);
            if ($page !== null) {
                return $page;
            }
        }

        return null;
    }

    private function findFirstDisplayablePageInTree(Menu $menu, string $locale): ?Menu
    {
        if (!$menu->isActive() || ($menu->getLocale() ?? 'fr') !== $locale) {
            return null;
        }

        if ($menu->isPage()
            && $this->frontMenuService->isMenuHierarchyFullyActive($menu)
            && $this->menuHasDisplayableContent($menu, $locale)) {
            return $menu;
        }

        foreach ($menu->getChildren() as $child) {
            if (!$child->isActive()) {
                continue;
            }
            $page = $this->findFirstDisplayablePageInTree($child, $locale);
            if ($page !== null) {
                return $page;
            }
        }

        return null;
    }

    private function menuHasDisplayableContent(Menu $menu, string $locale): bool
    {
        return $this->frontMenuService->getVisibleFrontSections($menu, $locale) !== [];
    }

    private function isValidTargetPage(?Menu $menu, string $locale): bool
    {
        return $menu instanceof Menu
            && $menu->isPage()
            && $this->frontMenuService->isMenuHierarchyFullyActive($menu)
            && $this->menuHasDisplayableContent($menu, $locale);
    }

    private function findMenuByResolvedReference(string $targetLocale, string $resolvedReference): ?Menu
    {
        $resolvedReference = strtolower(trim($resolvedReference));
        if ($resolvedReference === '') {
            return null;
        }

        $byColumn = $this->menuRepository->findOneByLocaleAndReferenceName($targetLocale, $resolvedReference);
        if ($byColumn instanceof Menu) {
            return $byColumn;
        }

        foreach ($this->menuRepository->findByLocale($targetLocale) as $candidate) {
            if ($this->referenceNameResolver->resolve($candidate) === $resolvedReference) {
                return $candidate;
            }
        }

        return null;
    }

    private function isContactMenu(Menu $menu): bool
    {
        return $this->contactProvisioner->isContactMenuName($menu->getName())
            || strtolower(trim((string) $menu->getSlug())) === MenuContactProvisioner::CONTACT_MENU_NAME;
    }

    private function buildMenuUrl(Menu $menu, string $locale): string
    {
        if ($this->isContactMenu($menu)) {
            return $this->urlGenerator->generate('front_contact', ['_locale' => $locale]);
        }

        return $this->urlGenerator->generate('front_menu', [
            '_locale' => $locale,
            'slugs' => $this->buildSlugPath($menu),
        ]);
    }

    private function buildSlugPath(Menu $menu): string
    {
        $parts = [];
        $current = $menu;
        while ($current !== null) {
            array_unshift($parts, (string) $current->getSlug());
            $current = $current->getParent();
        }

        return implode('/', $parts);
    }
}
