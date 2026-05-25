<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Menu;
use App\Repository\MenuRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Plan du site (équivalent Hermes 2.2.7 Page::getSitemapByLocale).
 *
 * @phpstan-type SitemapEntry array{
 *   name: string,
 *   sheetname: string,
 *   loc: string,
 *   lastmod: string,
 *   changefreq: string,
 *   priority: string,
 * }
 */
final class SitemapBuilder
{
    public function __construct(
        private readonly MenuRepository $menuRepository,
        private readonly FrontMenuService $frontMenuService,
        private readonly MenuContactProvisioner $contactProvisioner,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return array{xml: list<SitemapEntry>, html: array<string, list<SitemapEntry>>}
     */
    public function build(string $locale, ?string $schemeAndHost = null): array
    {
        $pages = [];
        foreach ($this->menuRepository->findRoots() as $root) {
            $this->collectPages($root, $locale, $pages);
        }

        $contact = $this->frontMenuService->findContactPage($locale);
        if ($contact !== null && !$this->containsMenu($pages, $contact)) {
            $pages[] = $contact;
        }

        $xml = [];
        $html = [];
        $host = $schemeAndHost ?? '';

        foreach ($pages as $index => $menu) {
            $root = $menu->getInitialParent();
            $entry = [
                'name' => $menu->getName() ?? '',
                'sheetname' => $root->getName() ?? '',
                'loc' => $host.$this->buildPageUrl($menu, $locale, $index === 0),
                'lastmod' => $this->formatLastMod($menu),
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ];
            $xml[] = $entry;
            $html[$root->getSlug() ?? 'root'][] = $entry;
        }

        return ['xml' => $xml, 'html' => $html];
    }

    private function collectPages(Menu $menu, string $locale, array &$pages): void
    {
        if (!$menu->isActive() || $menu->getLocale() !== $locale) {
            return;
        }

        if ($menu->isPage() && $this->frontMenuService->isMenuHierarchyFullyActive($menu)) {
            $pages[] = $menu;
        }

        foreach ($menu->getChildren() as $child) {
            $this->collectPages($child, $locale, $pages);
        }
    }

    /**
     * @param list<Menu> $pages
     */
    private function containsMenu(array $pages, Menu $needle): bool
    {
        foreach ($pages as $menu) {
            if ($menu->getId() !== null && $menu->getId() === $needle->getId()) {
                return true;
            }
        }

        return false;
    }

    private function buildPageUrl(Menu $menu, string $locale, bool $isFirstInLocale): string
    {
        if ($isFirstInLocale) {
            return $this->urlGenerator->generate(
                'front_home',
                ['_locale' => $locale],
                UrlGeneratorInterface::ABSOLUTE_PATH,
            );
        }

        if ($this->contactProvisioner->isContactMenuName($menu->getName())
            || strtolower((string) $menu->getSlug()) === MenuContactProvisioner::CONTACT_MENU_NAME) {
            return $this->urlGenerator->generate(
                'front_contact',
                ['_locale' => $locale],
                UrlGeneratorInterface::ABSOLUTE_PATH,
            );
        }

        return $this->urlGenerator->generate(
            'front_menu',
            ['_locale' => $locale, 'slugs' => $this->buildSlugPath($menu)],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );
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

    private function formatLastMod(Menu $menu): string
    {
        $updated = $menu->getUpdatedAt();

        return $updated !== null ? $updated->format('Y-m-d') : (new \DateTimeImmutable('now'))->format('Y-m-d');
    }
}
