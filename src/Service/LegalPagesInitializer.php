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

/**
 * Crée les pages légales (menus inactifs, hors navbar) avec section libre + post depuis l’API Hermes.
 */
final class LegalPagesInitializer
{
    /** @var list<array{slug: string, name: string, reference: string}> */
    public const PAGES = [
        ['slug' => 'mentions-legales', 'name' => 'Mentions légales', 'reference' => 'mentions-legales'],
        ['slug' => 'confidentialite', 'name' => 'Confidentialité', 'reference' => 'confidentialite'],
        ['slug' => 'cgu-cgv', 'name' => 'CGU-CGV', 'reference' => 'cgu-cgv'],
    ];

    private const FALLBACK_HTML = '<p>Contenu à compléter depuis l’administration (modèle API Hermes non disponible).</p>';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MenuRepository $menuRepository,
        private readonly MenuManager $menuManager,
        private readonly TemplateRepository $templateRepository,
        private readonly HermesApiClient $hermesApiClient,
        private readonly LegalPagesApiTemplateMatcher $apiTemplateMatcher,
    ) {
    }

    /**
     * @return array{
     *     created: int,
     *     skipped: int,
     *     pages: array<string, array{slug: string, template_iri: string|null, template_label: string|null, api_matched: bool, skipped: bool}>
     * }
     */
    public function initialize(string $locale = 'fr'): array
    {
        $libreTemplate = $this->templateRepository->findOneBy(['code' => 'libre']);
        if (!$libreTemplate instanceof Template) {
            throw new \RuntimeException('Gabarit « libre » introuvable. Exécutez d’abord app:init-hermes.');
        }

        $catalogSummaries = $this->hermesApiClient->isLibreCatalogConfigured()
            ? $this->hermesApiClient->fetchLibreTemplateSummaries()
            : [];

        $templatesBySlug = $this->apiTemplateMatcher->resolveForPages(self::PAGES, $catalogSummaries);

        $created = 0;
        $skipped = 0;
        $pageReports = [];

        foreach (self::PAGES as $page) {
            $slug = $page['slug'];

            if ($this->menuRepository->findOneBySlugPath($locale, [$slug], false) !== null) {
                ++$skipped;
                $pageReports[$slug] = [
                    'slug' => $slug,
                    'template_iri' => null,
                    'template_label' => null,
                    'api_matched' => false,
                    'skipped' => true,
                ];
                continue;
            }

            $templateMeta = $templatesBySlug[$slug] ?? null;
            $html = self::FALLBACK_HTML;
            if ($templateMeta !== null) {
                $html = $this->hermesApiClient->fetchLibreTemplateHtml($templateMeta['iri']) ?? self::FALLBACK_HTML;
            }

            $menu = new Menu();
            $menu->setName($page['name']);
            $menu->setCode($page['slug']);
            $menu->setLocale($locale);
            $menu->setActive(false);
            $menu->setReferenceName($page['reference']);

            $this->menuManager->create($menu);
            $this->forceMenuSlug($menu, $page['slug']);
            $this->menuRepository->save($menu);

            $section = new Section();
            $section->setMenu($menu);
            $section->setTemplate($libreTemplate);
            $section->setPosition(1);
            $section->setTemplateWidth(10);
            $section->setActive(true);

            $post = new Post();
            $post->setName($page['name']);
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

            ++$created;
            $pageReports[$slug] = [
                'slug' => $slug,
                'template_iri' => $templateMeta['iri'] ?? null,
                'template_label' => $templateMeta['label'] ?? null,
                'api_matched' => $templateMeta !== null && $html !== self::FALLBACK_HTML,
                'skipped' => false,
            ];
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'pages' => $pageReports,
        ];
    }

    private function forceMenuSlug(Menu $menu, string $slug): void
    {
        if ($menu->getSlug() === $slug) {
            return;
        }

        $property = new \ReflectionProperty(Menu::class, 'slug');
        $property->setAccessible(true);
        $property->setValue($menu, $slug);
    }
}
