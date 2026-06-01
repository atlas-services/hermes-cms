<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Menu;

/**
 * Identifiant stable d’un menu pour lier les traductions (copie locale, switcher front).
 */
final class MenuReferenceNameResolver
{
    public function resolve(Menu $menu): string
    {
        $referenceName = strtolower(trim($menu->getReferenceName()));
        if ($referenceName !== '' && $referenceName !== 'ref') {
            return $referenceName;
        }

        $code = strtolower(trim((string) ($menu->getCode() ?? '')));
        if ($code !== '') {
            return $code;
        }

        $name = strtolower(trim((string) $menu->getName()));
        $name = trim((string) preg_replace('/[^a-z0-9]+/', '-', $name), '-');
        if ($name !== '') {
            return $name;
        }

        $slug = strtolower(trim((string) ($menu->getSlug() ?? '')));
        if ($slug !== '') {
            return $slug;
        }

        return 'menu-' . ($menu->getId() ?? uniqid());
    }
}
