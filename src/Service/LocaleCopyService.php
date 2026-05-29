<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Repository\MenuRepository;
use App\Repository\SectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Duplication d’une locale source vers une locale cible (modèle Hermes 2.2.7).
 */
final class LocaleCopyService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MenuRepository $menuRepository,
        private readonly SectionRepository $sectionRepository,
        #[Autowire(param: 'app.locales')]
        private readonly array $appLocales,
        #[Autowire(param: 'app.default_locale')]
        private readonly string $defaultLocale,
    ) {
    }

    public function resolveDefaultSourceLocale(): string
    {
        $active = $this->menuRepository->findOneBy(['active' => true], ['position' => 'ASC']);
        if ($active instanceof Menu && $active->getLocale() !== null && $active->getLocale() !== '') {
            return $active->getLocale();
        }

        foreach ($this->appLocales as $locale) {
            if ($this->menuRepository->countByLocale($locale) > 0) {
                return $locale;
            }
        }

        return $this->defaultLocale;
    }

    /**
     * @return array{success?: string, warning?: string}
     */
    public function copyLocale(string $targetLocale, ?string $sourceLocale = null): array
    {
        $targetLocale = strtolower(trim($targetLocale));
        if (!\in_array($targetLocale, $this->appLocales, true)) {
            return ['warning' => sprintf('Locale « %s » non autorisée.', $targetLocale)];
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

        foreach ($this->appLocales as $locale) {
            if ($locale !== $targetLocale && $this->menuRepository->countByLocale($locale) > 0) {
                return $locale;
            }
        }

        return $this->defaultLocale;
    }

    private function copyMenus(string $sourceLocale, string $targetLocale): int
    {
        $menus = $this->menuRepository->findByLocale($sourceLocale);
        $created = 0;
        usort($menus, static fn (Menu $a, Menu $b): int => $a->getDepth() <=> $b->getDepth());

        foreach ($menus as $menu) {
            $menu->syncReferenceName();
            $this->entityManager->persist($menu);

            if ($this->menuRepository->findOneByLocaleAndReferenceName($targetLocale, $menu->getReferenceName()) !== null) {
                continue;
            }

            $clone = new Menu();
            $clone->setActive($menu->isActive());
            $clone->setCode($menu->getCode());
            $clone->setName($menu->getName() . '-' . $targetLocale);
            $clone->setPosition($menu->getPosition());
            $clone->setLocale($targetLocale);
            $clone->setReferenceName($menu->getReferenceName());

            $parent = $menu->getParent();
            if ($parent !== null) {
                $parent->syncReferenceName();
                $targetParent = $this->menuRepository->findOneByLocaleAndReferenceName(
                    $targetLocale,
                    $parent->getReferenceName(),
                );
                if ($targetParent !== null) {
                    $clone->setParent($targetParent);
                }
            }

            $this->entityManager->persist($clone);
            ++$created;
        }

        return $created;
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
                $sourceMenu->getReferenceName(),
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
            $ref = $sourceSection->getReferenceName();
            $sourceSection->ensureFooterReferenceName();
            $ref ??= $sourceSection->getReferenceName();
            if ($ref === null) {
                continue;
            }

            if ($this->sectionRepository->findOneFooterByLocaleAndReference($targetLocale, $ref) !== null) {
                continue;
            }

            $targetSection = $this->cloneSectionShell($sourceSection, null, $targetLocale);
            $targetSection->setReferenceName($ref);
            $this->entityManager->persist($targetSection);
            ++$sectionsCreated;
            $this->entityManager->flush();
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
        $section->setTemplateNbCol($source->getTemplateNbCol());
        $section->setTemplateImageFilter($source->getTemplateImageFilter());
        $section->setReferenceName($source->getReferenceName());

        return $section;
    }

    private function copyPosts(Section $sourceSection, Section $targetSection, string $targetLocale): int
    {
        $created = 0;
        foreach ($sourceSection->getPosts() as $sourcePost) {
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
}
