<?php

declare(strict_types=1);

namespace App\Service\ContentTransfer;

use App\Entity\Menu;
use App\Entity\Section;
use App\Repository\MenuRepository;
use App\Service\AppLocaleService;
use App\Service\MenuTreeBuilder;

/**
 * Cibles possibles pour copie / déplacement (pages menu par langue).
 */
final class ContentTransferTargetProvider
{
    public function __construct(
        private readonly MenuRepository $menuRepository,
        private readonly AppLocaleService $appLocaleService,
        private readonly MenuTreeBuilder $menuTreeBuilder,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getLocaleChoices(): array
    {
        return $this->appLocaleService->getContentLocales();
    }

    /**
     * @return list<array{id: int, locale: string, label: string, displayLabel: string, sections: list<array{id: int, label: string}>}>
     */
    public function getPagesPayload(): array
    {
        $payload = [];
        $seen = [];

        foreach ($this->getLocaleChoices() as $locale) {
            $pages = $this->menuRepository->findPages($locale);
            $ordered = $this->menuTreeBuilder->orderPagesByTree($pages, $locale);

            foreach ($ordered as $choice) {
                $menu = $choice['menu'];
                $id = $menu->getId();
                if ($id === null || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;

                $sections = [];
                foreach ($menu->getSections() as $section) {
                    if ($section->isGlobalSection()) {
                        continue;
                    }
                    $sid = $section->getId();
                    if ($sid === null) {
                        continue;
                    }
                    $tpl = $section->getTemplate();
                    $sections[] = [
                        'id' => $sid,
                        'label' => sprintf(
                            '%s — %s',
                            $section->getPosition(),
                            $tpl?->getName() ?? '—',
                        ),
                    ];
                }

                $payload[] = [
                    'id' => $id,
                    'locale' => $locale,
                    'label' => sprintf('[%s] %s', strtoupper($locale), $choice['label']),
                    'displayLabel' => $choice['label'],
                    'sections' => $sections,
                ];
            }
        }

        return $payload;
    }

    public function findTargetMenu(int $menuId): ?Menu
    {
        $menu = $this->menuRepository->find($menuId);

        if ($menu === null || !$menu->isPage()) {
            return null;
        }

        return $menu;
    }

    public function findTargetSection(int $sectionId, Menu $targetMenu): ?Section
    {
        foreach ($targetMenu->getSections() as $section) {
            if ($section->getId() === $sectionId && !$section->isGlobalSection()) {
                return $section;
            }
        }

        return null;
    }
}
