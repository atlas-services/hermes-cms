<?php

namespace App\Service;

use App\Entity\Menu;
use App\Exception\MaxDepthExceededException;
use App\Repository\MenuRepository;

class MenuManager
{
    public function __construct(
        private MenuRepository $menuRepository,
        private MenuContactProvisioner $menuContactProvisioner,
        private int $maxDepth
    ) {}

    // -------------------------
    // STRUCTURE
    // -------------------------

    public function canAddChild(Menu $parent): bool
    {
        return $parent->getDepth() + 1 < $this->maxDepth;
    }

    public function assertCanAddChild(Menu $parent): void
    {
        if (!$this->canAddChild($parent)) {
            throw new MaxDepthExceededException();
        }
    }

    // -------------------------
    // CREATE
    // -------------------------

    public function create(Menu $menu): void
    {
        $parent = $menu->getParent();

        if ($parent) {
            $this->assertCanAddChild($parent);
        }

        $menu->setPosition(
            $this->menuRepository->getNextPosition($parent)
        );

        $this->menuRepository->save($menu);
        $this->menuContactProvisioner->provisionIfContactMenu($menu);
    }

    // -------------------------
    // READ
    // -------------------------

    /**
     * 🌳 Tree complet (racines)
     */
    public function getTree(): array
    {
        return $this->menuRepository->findRoots();
    }
}
