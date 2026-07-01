<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Repository\MenuRepository;
use App\Repository\SectionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Duplication d’une locale source vers une locale cible (modèle Hermes 2.2.7).
 */
final class LocaleCopyService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MenuRepository $menuRepository,
        private readonly SectionRepository $sectionRepository,
        private readonly AppLocaleService $appLocaleService,
        private readonly MenuReferenceNameResolver $referenceNameResolver,
    ) {
    }

    public function resolveDefaultSourceLocale(): string
    {
        $active = $this->menuRepository->findOneBy(['active' => true], ['position' => 'ASC']);
        if ($active instanceof Menu && $active->getLocale() !== null && $active->getLocale() !== '') {
            return $active->getLocale();
        }

        foreach ($this->appLocaleService->getContentLocales() as $locale) {
            if ($this->menuRepository->countByLocale($locale) > 0) {
                return $locale;
            }
        }

        return $this->appLocaleService->getDefaultLocale();
    }

    /**
     * @return array{success?: string, warning?: string}
     */
    public function copyLocale(string $targetLocale, ?string $sourceLocale = null): array
    {
        $targetLocale = strtolower(trim($targetLocale));
        if (!$this->appLocaleService->isValidLanguageCode($targetLocale)) {
            return ['warning' => sprintf('Locale « %s » non reconnue.', $targetLocale)];
        }

        if (\in_array($targetLocale, $this->appLocaleService->getContentLocales(), true)) {
            return ['warning' => sprintf('La langue « %s » possède déjà des menus ou des posts.', $targetLocale)];
        }

        $sourceLocale ??= $this->resolveSourceLocale($targetLocale);
        if ($sourceLocale === $targetLocale) {
            return ['warning' => 'La langue source et la langue cible sont identiques.'];
        }

        $stats = ['menus' => 0, 'sections' => 0, 'posts' => 0];

        try {
            $this->entityManager->wrapInTransaction(function () use ($sourceLocale, $targetLocale, &$stats): void {
                $stats['menus'] = $this->copyMenus($sourceLocale, $targetLocale);
                $this->entityManager->flush();
                $pageCopy = $this->copyPageSections($sourceLocale, $targetLocale);
                $stats['sections'] += $pageCopy['sections'];
                $stats['posts'] += $pageCopy['posts'];
                $this->entityManager->flush();
                $footerCopy = $this->copyFooterSections($sourceLocale, $targetLocale);
                $stats['sections'] += $footerCopy['sections'];
                $stats['posts'] += $footerCopy['posts'];
                $this->entityManager->flush();
            });

            if ($stats['menus'] === 0 && $stats['posts'] === 0) {
                return [
                    'warning' => sprintf(
                        'Aucun contenu copié depuis « %s » vers « %s » (menus déjà présents ou source vide).',
                        $sourceLocale,
                        $targetLocale,
                    ),
                ];
            }

            return [
                'success' => sprintf(
                    'Langue « %s » copiée depuis « %s » : %d menu(s), %d section(s), %d post(s).',
                    $targetLocale,
                    $sourceLocale,
                    $stats['menus'],
                    $stats['sections'],
                    $stats['posts'],
                ),
            ];
        } catch (\Throwable $e) {
            return ['warning' => $e->getMessage()];
        }
    }

    private function resolveSourceLocale(string $targetLocale): string
    {
        $active = $this->menuRepository->findOneBy(['active' => true], ['position' => 'ASC']);
        if ($active instanceof Menu && $active->getLocale() !== null && $active->getLocale() !== $targetLocale) {
            return $active->getLocale();
        }

        foreach ($this->appLocaleService->getContentLocales() as $locale) {
            if ($locale !== $targetLocale && $this->menuRepository->countByLocale($locale) > 0) {
                return $locale;
            }
        }

        return $this->appLocaleService->getDefaultLocale();
    }

    private function copyMenus(string $sourceLocale, string $targetLocale): int
    {
        $menus = $this->menuRepository->findByLocale($sourceLocale);
        usort($menus, static function (Menu $a, Menu $b): int {
            $depthCmp = $a->getDepth() <=> $b->getDepth();
            if ($depthCmp !== 0) {
                return $depthCmp;
            }

            $positionCmp = $a->getPosition() <=> $b->getPosition();
            if ($positionCmp !== 0) {
                return $positionCmp;
            }

            return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
        });

        /** @var array<int, Menu> $targetBySourceId */
        $targetBySourceId = [];
        $created = 0;

        foreach ($menus as $menu) {
            $sourceId = $menu->getId();
            if ($sourceId === null) {
                continue;
            }

            $referenceName = $this->resolveStableReferenceName($menu);
            $existing = $this->menuRepository->findOneByLocaleAndReferenceName($targetLocale, $referenceName);
            if ($existing !== null) {
                $targetBySourceId[$sourceId] = $existing;
                continue;
            }

            $clone = new Menu();
            $clone->setActive($menu->isActive());
            $clone->setCode($menu->getCode());
            $clone->setName($menu->getName() . '-' . $targetLocale);
            $clone->setPosition($menu->getPosition());
            $clone->setLocale($targetLocale);
            $clone->setReferenceName($referenceName);

            $parent = $menu->getParent();
            if ($parent !== null) {
                $parentId = $parent->getId();
                if ($parentId !== null && isset($targetBySourceId[$parentId])) {
                    $clone->setParent($targetBySourceId[$parentId]);
                }
            }

            $this->entityManager->persist($clone);
            $targetBySourceId[$sourceId] = $clone;
            ++$created;
        }

        return $created;
    }

    private function resolveStableReferenceName(Menu $menu): string
    {
        return $this->referenceNameResolver->resolve($menu);
    }

    /**
     * @return array{sections: int, posts: int}
     */
    private function copyPageSections(string $sourceLocale, string $targetLocale): array
    {
        $sectionsCreated = 0;
        $postsCreated = 0;
        $menus = $this->menuRepository->findByLocale($sourceLocale);
        foreach ($menus as $sourceMenu) {
            if (!$sourceMenu->isPage()) {
                continue;
            }

            $targetMenu = $this->menuRepository->findOneByLocaleAndReferenceName(
                $targetLocale,
                $this->resolveStableReferenceName($sourceMenu),
            );
            if ($targetMenu === null) {
                continue;
            }

            foreach ($sourceMenu->getSections() as $sourceSection) {
                if ($sourceSection->isFooterSection()) {
                    continue;
                }

                $targetSection = $this->findMatchingSectionOnMenu($sourceSection, $targetMenu);
                if ($targetSection === null) {
                    $targetSection = $this->cloneSectionShell($sourceSection, $targetMenu, null);
                    $this->entityManager->persist($targetSection);
                    ++$sectionsCreated;
                }

                $postsCreated += $this->copyPosts($sourceSection, $targetSection, $targetLocale);
            }
        }

        return ['sections' => $sectionsCreated, 'posts' => $postsCreated];
    }

    /**
     * @return array{sections: int, posts: int}
     */
    private function copyFooterSections(string $sourceLocale, string $targetLocale): array
    {
        $sectionsCreated = 0;
        $postsCreated = 0;
        foreach ($this->sectionRepository->findFooterSectionsForLocale($sourceLocale) as $sourceSection) {
            $sourceSection->ensureFooterReferenceName();
            $ref = $sourceSection->getReferenceName();
            if ($ref === null) {
                continue;
            }

            $targetSection = $this->sectionRepository->findOneFooterByLocaleAndReference($targetLocale, $ref);
            if ($targetSection === null) {
                $targetSection = $this->cloneSectionShell($sourceSection, null, $targetLocale);
                $targetSection->setReferenceName($ref);
                $this->entityManager->persist($targetSection);
                ++$sectionsCreated;
                $this->entityManager->flush();
            }
            $postsCreated += $this->copyPosts($sourceSection, $targetSection, $targetLocale);
        }

        return ['sections' => $sectionsCreated, 'posts' => $postsCreated];
    }

    private function findMatchingSectionOnMenu(Section $sourceSection, Menu $targetMenu): ?Section
    {
        $sourcePos = $sourceSection->getPosition();
        foreach ($targetMenu->getSections() as $section) {
            if ($section->getPosition() === $sourcePos) {
                return $section;
            }
        }

        return null;
    }

    private function cloneSectionShell(Section $source, ?Menu $targetMenu, ?string $footerLocale): Section
    {
        $section = new Section();
        $section->setMenu($targetMenu);
        if ($footerLocale !== null) {
            $section->setLocale($footerLocale);
        }
        $section->setTemplate($source->getTemplate());
        $section->setTemplate2($source->getTemplate2());
        $section->setTemplateWidth($source->getTemplateWidth());
        $section->setTemplate2Width($source->getRawTemplate2Width());
        $section->setPosition($source->getPosition());
        $section->setActive($source->isActive());
        $section->setTransparent($source->isTransparent());
        $section->setTemplateBgcolor($source->getRawTemplateBgcolor());
        $section->setTemplateColor($source->getRawTemplateColor());
        $section->setTemplateNbCol($source->getTemplateNbCol());
        $section->setTemplateImageFilter($source->getTemplateImageFilter());
        $section->setReferenceName($source->getReferenceName());

        return $section;
    }

    private function copyPosts(Section $sourceSection, Section $targetSection, string $targetLocale): int
    {
        $created = 0;
        foreach ($sourceSection->getPosts() as $sourcePost) {
            if ($this->hasEquivalentPostOnSection($targetSection, $sourcePost)) {
                continue;
            }
            $post = new Post();
            $post->setName($sourcePost->getName());
            $post->setContent($sourcePost->getContent());
            $post->setActive($sourcePost->isActive());
            $post->setStartPublishedAt($sourcePost->getStartPublishedAt());
            $post->setEndPublishedAt($sourcePost->getEndPublishedAt());
            $post->setPosition($sourcePost->getPosition());
            $post->setLocale($targetLocale);
            $targetSection->addPost($post);
            $this->entityManager->persist($post);
            ++$created;
        }

        return $created;
    }

    private function hasEquivalentPostOnSection(Section $targetSection, Post $sourcePost): bool
    {
        foreach ($targetSection->getPosts() as $existing) {
            if ($existing->getPosition() !== $sourcePost->getPosition()) {
                continue;
            }

            if (trim((string) $existing->getName()) !== trim((string) $sourcePost->getName())) {
                continue;
            }

            return true;
        }

        return false;
    }
}
