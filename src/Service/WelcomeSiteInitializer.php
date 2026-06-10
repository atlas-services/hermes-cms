<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use App\Repository\MenuRepository;
use App\Repository\TemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Page d’accueil « en construction » (menu ACCUEIL) — uniquement si aucun menu n’existe encore.
 */
final class WelcomeSiteInitializer
{
    public const MENU_NAME = 'ACCUEIL';
    public const MENU_SLUG = 'accueil';
    public const MENU_REFERENCE = 'accueil';
    public const TEMPLATE_FILE = 'templates/exemple/site_en_construction_accueil.html';
    public const PLACEHOLDER = '__APP_NAME__';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MenuRepository $menuRepository,
        private readonly MenuManager $menuManager,
        private readonly TemplateRepository $templateRepository,
        #[Autowire(param: 'app.name')]
        private readonly string $appName,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array{created: bool, skipped_reason: string|null, menu_id: int|null}
     */
    public function initializeIfEmpty(string $locale = 'fr'): array
    {
        if ($this->menuRepository->count([]) > 0) {
            return [
                'created' => false,
                'skipped_reason' => 'menus_exist',
                'menu_id' => null,
            ];
        }

        $libreTemplate = $this->templateRepository->findOneBy(['code' => 'libre']);
        if (!$libreTemplate instanceof Template) {
            throw new \RuntimeException('Gabarit « libre » introuvable. Exécutez d’abord app:init-hermes.');
        }

        $html = $this->buildWelcomeHtml();

        $menu = new Menu();
        $menu->setName(self::MENU_NAME);
        $menu->setCode(self::MENU_SLUG);
        $menu->setLocale($locale);
        $menu->setActive(true);
        $menu->setReferenceName(self::MENU_REFERENCE);

        $this->menuManager->create($menu);
        $this->forceMenuSlug($menu, self::MENU_SLUG);

        $section = new Section();
        $section->setMenu($menu);
        $section->setTemplate($libreTemplate);
        $section->setPosition(1);
        $section->setTemplateWidth(12);
        $section->setActive(true);

        $post = new Post();
        $post->setName(self::MENU_NAME);
        $post->setSection($section);
        $post->setPosition(1);
        $post->setActive(true);
        $post->setLocale($locale);
        $post->setContent($html);

        $menu->addSection($section);
        $section->addPost($post);

        $this->entityManager->persist($section);
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return [
            'created' => true,
            'skipped_reason' => null,
            'menu_id' => $menu->getId(),
        ];
    }

    private function buildWelcomeHtml(): string
    {
        $path = rtrim($this->projectDir, '/') . '/' . self::TEMPLATE_FILE;
        if (!is_readable($path)) {
            throw new \RuntimeException(sprintf('Modèle d’accueil introuvable : %s', self::TEMPLATE_FILE));
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException(sprintf('Impossible de lire le modèle : %s', self::TEMPLATE_FILE));
        }

        $siteName = trim($this->appName) !== '' ? trim($this->appName) : 'monsite';

        return str_replace(self::PLACEHOLDER, htmlspecialchars($siteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $raw);
    }

    private function forceMenuSlug(Menu $menu, string $slug): void
    {
        if ($menu->getSlug() === $slug) {
            return;
        }

        $property = new \ReflectionProperty(Menu::class, 'slug');
        $property->setAccessible(true);
        $property->setValue($menu, $slug);
        $this->menuRepository->save($menu);
    }
}
