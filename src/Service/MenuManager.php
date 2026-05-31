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
        if ($menu->getId() !== null) {
            throw new \InvalidArgumentException('Utiliser update() pour modifier un menu existant.');
        }

        $parent = $menu->getParent();

        if ($parent) {
            $this->assertCanAddChild($parent);
        }

        $this->assignUniqueReferenceName($menu);

        $menu->setPosition(
            $this->menuRepository->getNextPosition($parent)
        );

        $this->menuRepository->save($menu);
        $this->menuContactProvisioner->provisionIfContactMenu($menu);
    }

    public function update(Menu $menu): void
    {
        if ($menu->getId() === null) {
            throw new \InvalidArgumentException('Utiliser create() pour un nouveau menu.');
        }

        $parent = $menu->getParent();

        if ($parent) {
            $this->assertCanAddChild($parent);
        }

        $this->assignUniqueReferenceName($menu);
        $this->menuRepository->save($menu);
        $this->menuContactProvisioner->provisionIfContactMenu($menu);
    }

    /** Calcule un referenceName unique pour la locale (avant validation / persist). */
    public function assignUniqueReferenceName(Menu $menu): void
    {
        $menu->syncReferenceName();

        $locale = $menu->getLocale() ?? 'fr';
        $base = $menu->getReferenceName();
        $candidate = $base;
        $suffix = 2;

        while (true) {
            $existing = $this->menuRepository->findOneByLocaleAndReferenceName($locale, $candidate);
            if ($existing === null || ($menu->getId() !== null && $existing->getId() === $menu->getId())) {
                $menu->setReferenceName($candidate);

                return;
            }
            $candidate = $base . '-' . $suffix;
            ++$suffix;
        }
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
