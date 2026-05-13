<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Menu;
use App\Repository\MenuRepository;

final class FrontMenuService
{
    public function __construct(private MenuRepository $menuRepository)
    {
    }

    public function findMenuBySlugs(array $slugs, string $locale): ?Menu
    {
        $currentMenu = null;

        foreach ($slugs as $slug) {
            $criteria = ['slug' => $slug, 'locale' => $locale, 'active' => true, 'parent' => $currentMenu];
            if ($currentMenu === null) {
                $criteria['parent'] = null;
            }

            $currentMenu = $this->menuRepository->findOneBy($criteria);

            if (!$currentMenu) {
                return null;
            }
        }

        return $currentMenu;
    }

    public function findFirstAccessiblePage(string $locale): ?Menu
    {
        foreach ($this->menuRepository->findRoots() as $root) {
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
     * Menu page + sections + posts visibles sur le site (tous actifs).
     *
     * @return list<array{section: Section, posts: list<Post>}>
     */
    public function getVisibleFrontSections(Menu $menu): array
    {
        $blocks = [];
        foreach ($menu->getSections() as $section) {
            if (!$section->isActive()) {
                continue;
            }
            $posts = [];
            foreach ($section->getPosts() as $post) {
                if ($post->isActive()) {
                    $posts[] = $post;
                }
            }
            $blocks[] = ['section' => $section, 'posts' => $posts];
        }

        return $blocks;
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
}
