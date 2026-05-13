<?php

namespace App\Service;

use App\Entity\Menu;
use App\Repository\MenuRepository;

class MenuTreeBuilder
{
    public function __construct(
        private MenuRepository $repository
    ) {}

    /**
     * @return MenuNode[]
     */
    public function buildTree(bool $onlyActiveMenus = false): array
    {
        $roots = $this->repository->findRoots();

        $nodes = [];
        foreach ($roots as $menu) {
            if ($onlyActiveMenus && !$menu->isActive()) {
                continue;
            }
            $nodes[] = $this->buildNode($menu, [], $onlyActiveMenus);
        }

        return $nodes;
    }

    private function buildNode(Menu $menu, array $path = [], bool $onlyActiveMenus = false): MenuNode
    {
        $node = new MenuNode();
        $node->menu = $menu;

        $slugParts = array_merge($path, [$menu->getSlug()]);
        $node->slugPath = implode('/', $slugParts);

        $node->type = $this->resolveType($menu);
        $node->canAddPage = $this->canAddPage($menu);

        foreach ($menu->getChildren() as $child) {
            if ($onlyActiveMenus && !$child->isActive()) {
                continue;
            }
            $node->children[] = $this->buildNode($child, $slugParts, $onlyActiveMenus);
        }

        return $node;
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
}
