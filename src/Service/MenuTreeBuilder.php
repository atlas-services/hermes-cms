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
    public function buildTree(): array
    {
        $roots = $this->repository->findRoots();

        return array_map(
            fn(Menu $menu) => $this->buildNode($menu),
            $roots
        );
    }

    private function buildNode(Menu $menu): MenuNode
    {
        $node = new MenuNode();
        $node->menu = $menu;

        $node->type = $this->resolveType($menu);
        $node->canAddPage = $this->canAddPage($menu);

        foreach ($menu->getChildren() as $child) {
            $node->children[] = $this->buildNode($child);
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
