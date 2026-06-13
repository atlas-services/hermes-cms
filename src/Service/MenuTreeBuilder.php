<?php

namespace App\Service;

use App\Entity\Menu;
use App\Repository\MenuRepository;

class MenuTreeBuilder
{
    private const PAGE_CHOICE_PATH_SEPARATOR = ' / ';

    public function __construct(
        private MenuRepository $repository
    ) {}

    /**
     * @return MenuNode[]
     */
    public function buildTree(bool $onlyActiveMenus = false, ?string $locale = null): array
    {
        $roots = $locale !== null
            ? $this->repository->findRootsByLocale($locale)
            : $this->repository->findRoots();

        $nodes = [];
        foreach ($roots as $menu) {
            if ($onlyActiveMenus && !$menu->isActive()) {
                continue;
            }
            $nodes[] = $this->buildNode($menu, [], $onlyActiveMenus, $locale);
        }

        return $nodes;
    }

    /**
     * @param list<string> $path
     */
    private function buildNode(Menu $menu, array $path = [], bool $onlyActiveMenus = false, ?string $locale = null): MenuNode
    {
        $node = new MenuNode();
        $node->menu = $menu;

        $slugParts = array_merge($path, [$menu->getSlug()]);
        $node->slugPath = implode('/', $slugParts);

        $node->type = $this->resolveType($menu);
        $node->canAddPage = $this->canAddPage($menu);

        foreach ($menu->getChildren() as $child) {
            if ($locale !== null && !$this->menuMatchesLocale($child, $locale)) {
                continue;
            }
            if ($onlyActiveMenus && !$child->isActive()) {
                continue;
            }
            $node->children[] = $this->buildNode($child, $slugParts, $onlyActiveMenus, $locale);
        }

        return $node;
    }

    private function menuMatchesLocale(Menu $menu, string $locale): bool
    {
        return ($menu->getLocale() ?? 'fr') === $locale;
    }

    private function resolveType(Menu $menu): string
    {
        return match (true) {
            !$menu->getSections()->isEmpty() => 'page',
            $menu->isRoot() => 'root',
            default => 'navigation',
        };
    }

    private function canAddPage(Menu $menu): bool
    {
        return $menu->getChildren()->isEmpty();
    }

    /**
     * Réordonne les pages (menus avec sections) selon l’arborescence menu : racines puis enfants, par position.
     *
     * @param Menu[] $pages
     *
     * @return list<array{menu: Menu, depth: int, label: string}>
     */
    public function orderPagesByTree(array $pages, ?string $locale = null): array
    {
        /** @var array<int, Menu> $pagesById */
        $pagesById = [];
        foreach ($pages as $page) {
            $id = $page->getId();
            if ($id !== null) {
                $pagesById[$id] = $page;
            }
        }

        $ordered = [];
        $seen = [];

        foreach ($this->buildTree(false, $locale) as $root) {
            $this->collectPagesInTreeOrder($root, 0, [], $pagesById, $ordered, $seen);
        }

        foreach ($pagesById as $id => $page) {
            if (!isset($seen[$id])) {
                $ordered[] = [
                    'menu' => $page,
                    'depth' => 0,
                    'label' => $this->formatPageChoiceLabelFromMenu($page),
                ];
                $seen[$id] = true;
            }
        }

        return $ordered;
    }

    /**
     * @param array<int, Menu> $pagesById
     * @param list<array{menu: Menu, depth: int, label: string}> $ordered
     * @param array<int, true> $seen
     * @param list<string> $namePath Noms des ancêtres depuis la racine (sans le nœud courant).
     */
    private function collectPagesInTreeOrder(
        MenuNode $node,
        int $depth,
        array $namePath,
        array $pagesById,
        array &$ordered,
        array &$seen,
    ): void {
        $segment = trim((string) ($node->menu->getName() ?? ''));
        $pathToHere = $segment !== '' ? [...$namePath, $segment] : $namePath;

        $id = $node->menu->getId();
        if ($id !== null && $node->menu->isPage() && isset($pagesById[$id]) && !isset($seen[$id])) {
            $menu = $pagesById[$id];
            $ordered[] = [
                'menu' => $menu,
                'depth' => $depth,
                'label' => $this->formatPageChoiceLabelFromPath($pathToHere),
            ];
            $seen[$id] = true;
        }

        foreach ($node->children as $child) {
            $this->collectPagesInTreeOrder($child, $depth + 1, $pathToHere, $pagesById, $ordered, $seen);
        }
    }

    /**
     * @param list<string> $namePath
     */
    private function formatPageChoiceLabelFromPath(array $namePath): string
    {
        $parts = array_values(array_filter(
            $namePath,
            static fn (string $name): bool => trim($name) !== '',
        ));

        if ($parts === []) {
            return '';
        }

        return implode(self::PAGE_CHOICE_PATH_SEPARATOR, $parts);
    }

    private function formatPageChoiceLabelFromMenu(Menu $menu): string
    {
        $parts = array_map(
            static fn (Menu $ancestor): string => trim((string) ($ancestor->getName() ?? '')),
            $menu->getParents(),
        );
        $parts[] = trim((string) ($menu->getName() ?? ''));

        return $this->formatPageChoiceLabelFromPath($parts);
    }
}
