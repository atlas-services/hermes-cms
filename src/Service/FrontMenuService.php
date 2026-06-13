<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Repository\MenuRepository;
use App\Repository\SectionRepository;
use App\Service\MenuContactProvisioner;

final class FrontMenuService
{
    public function __construct(
        private MenuRepository $menuRepository,
        private SectionRepository $sectionRepository,
    ) {
    }

    /**
     * @param list<string> $slugs
     */
    public function findMenuBySlugs(array $slugs, string $locale): ?Menu
    {
        // L’accès direct au contenu reste possible même si le menu est désactivé.
        return $this->menuRepository->findOneBySlugPath($locale, $slugs, false);
    }

    public function findFirstAccessiblePage(string $locale): ?Menu
    {
        foreach ($this->menuRepository->findRootsByLocale($locale) as $root) {
            if (!$root->isActive()) {
                continue;
            }
            $page = $this->findFirstAccessiblePageInTree($root, $locale);
            if ($page !== null) {
                return $page;
            }
        }

        return null;
    }

    public function findFirstAccessiblePageAnyLocale(): ?Menu
    {
        foreach ($this->menuRepository->findRoots() as $root) {
            if (!$root->isActive()) {
                continue;
            }
            $page = $this->findFirstAccessiblePageInTreeWithoutLocale($root);
            if ($page !== null) {
                return $page;
            }
        }

        return null;
    }

    private function findFirstAccessiblePageInTree(Menu $menu, string $locale): ?Menu
    {
        if (!$menu->isActive() || $menu->getLocale() !== $locale) {
            return null;
        }

        if ($menu->isPage()) {
            return $menu;
        }

        foreach ($menu->getChildren() as $child) {
            if (!$child->isActive()) {
                continue;
            }
            $page = $this->findFirstAccessiblePageInTree($child, $locale);
            if ($page !== null) {
                return $page;
            }
        }

        return null;
    }

    private function findFirstAccessiblePageInTreeWithoutLocale(Menu $menu): ?Menu
    {
        if (!$menu->isActive()) {
            return null;
        }

        if ($menu->isPage()) {
            return $menu;
        }

        foreach ($menu->getChildren() as $child) {
            if (!$child->isActive()) {
                continue;
            }
            $page = $this->findFirstAccessiblePageInTreeWithoutLocale($child);
            if ($page !== null) {
                return $page;
            }
        }

        return null;
    }

    /**
     * Menu page + sections + posts visibles sur le site (section et posts actifs).
     * Les sections sans aucun post actif sont omises (le back-office peut les garder vides pour y ajouter des posts).
     *
     * @return list<array{section: Section, posts: list<Post>}>
     */
    public function getVisibleFrontSections(Menu $menu, ?string $locale = null): array
    {
        $locale ??= $menu->getLocale() ?? 'fr';
        $blocks = [];
        foreach ($menu->getSections() as $section) {
            if ($section->isFooterSection()) {
                continue;
            }
            if (!$section->isActive()) {
                continue;
            }
            $posts = [];
            foreach ($section->getPosts() as $post) {
                if ($post->isActive() && $this->postMatchesLocale($post, $menu, $locale)) {
                    $posts[] = $post;
                }
            }
            $templateType = strtolower(trim((string) ($section->getTemplate()?->getType() ?? '')));
            if ($posts === [] && $templateType !== 'formulaire') {
                continue;
            }
            $blocks[] = ['section' => $section, 'posts' => $posts];
        }

        return $blocks;
    }

    /**
     * Sections footer globales (toutes pages), même format que {@see getVisibleFrontSections()}.
     *
     * @return list<array{section: Section, posts: list<\App\Entity\Post>}>
     */
    public function getVisibleFooterSections(string $locale = 'fr'): array
    {
        $blocks = [];
        foreach ($this->sectionRepository->findFooterSectionsForLocale($locale) as $section) {
            if (($section->getLocale() ?? 'fr') !== $locale) {
                continue;
            }
            if (!$section->isActive()) {
                continue;
            }
            $posts = [];
            foreach ($section->getPosts() as $post) {
                if ($post->isActive() && $this->postMatchesLocale($post, null, $locale)) {
                    $posts[] = $post;
                }
            }
            if ($posts === []) {
                continue;
            }
            $blocks[] = ['section' => $section, 'posts' => $posts];
        }

        return $blocks;
    }

    public function findContactPage(string $locale): ?Menu
    {
        $menu = $this->menuRepository->findOneBy([
            'slug' => MenuContactProvisioner::CONTACT_MENU_NAME,
            'locale' => $locale,
        ]);

        if ($menu === null || !$menu->isPage()) {
            return null;
        }

        return $menu;
    }

    /** Tous les ancêtres du menu sont actifs (cohérence avec la résolution par slug). */
    public function isMenuHierarchyFullyActive(Menu $menu): bool
    {
        $m = $menu;
        while ($m !== null) {
            if (!$m->isActive()) {
                return false;
            }
            $m = $m->getParent();
        }

        return true;
    }

    private function postMatchesLocale(\App\Entity\Post $post, ?Menu $menu, string $locale): bool
    {
        $postLocale = $post->getLocale() ?? $menu?->getLocale() ?? 'fr';

        return $postLocale === $locale;
    }
}
