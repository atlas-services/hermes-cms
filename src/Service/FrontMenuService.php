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
            $criteria = ['slug' => $slug, 'locale' => $locale, 'parent' => $currentMenu];
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
}
