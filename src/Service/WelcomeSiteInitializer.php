<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Config;
use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use App\Repository\ConfigRepository;
use App\Repository\MenuRepository;
use App\Repository\SectionRepository;
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
    public const CONTACT_MENU_NAME = 'CONTACT';
    public const CONTACT_MENU_SLUG = 'contact';
    public const FOOTER_REFERENCE = 'footer-legal-pages';

    /** @var array<string, string> */
    private const WELCOME_CONFIG_VALUES = [
        'bgcolor' => '#000000',
        'text_color' => '#ffffff',
        'content_bgcolor' => '#000000',
        'content_color' => '#ffffff',
        'nav_bgcolor' => '#000000',
        'nav_li_bgcolor' => '#000000',
        'nav_bgcolor_shrink' => '#000000',
        'nav_bgcolor_active' => 'transparent',
        'nav_color_active' => '#ff00ff',
        'nav_bgcolor_link' => 'transparent',
        'nav_link_color' => '#ffffff',
        'nav_header_bgcolor' => '#000000',
        'nav_header_color' => '#ffffff',
        'footer_bgcolor' => '#000000',
        'footer_color' => '#ffffff',
        'footer_link_color' => '#ffffff',
        'footer_link_hover_color' => '#000000',
        'contact_bgcolor' => '#000000',
        'contact_color' => '#ffffff',
        'contact_bgcolor_subject' => '#000000',
        'contact_color_subject' => '#ffffff',
    ];

    /** @var list<array{slug: string, name: string, reference: string, template: string}> */
    private const LEGAL_PAGES = [
        [
            'slug' => 'mentions-legales',
            'name' => 'MENTIONS LÉGALES',
            'reference' => 'mentions-legales',
            'template' => 'templates/exemple/atlas_services_mentions_legales.html',
        ],
        [
            'slug' => 'confidentialite',
            'name' => 'CONFIDENTIALITÉ',
            'reference' => 'confidentialite',
            'template' => 'templates/exemple/atlas_services_confidentialite.html',
        ],
        [
            'slug' => 'cgu-cgv',
            'name' => 'CGU-CGV',
            'reference' => 'cgu-cgv',
            'template' => 'templates/exemple/atlas_services_cgu_cgv.html',
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfigRepository $configRepository,
        private readonly MenuRepository $menuRepository,
        private readonly MenuManager $menuManager,
        private readonly SectionRepository $sectionRepository,
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

        $this->applyWelcomeColors();

        $libreTemplate = $this->templateRepository->findOneBy(['code' => 'libre']);
        if (!$libreTemplate instanceof Template) {
            throw new \RuntimeException('Gabarit « libre » introuvable. Exécutez d’abord app:init-hermes.');
        }

        $html = $this->buildWelcomeHtml();

        $menu = new Menu();
        $menu->setName($this->getUppercaseSiteName());
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
        $section->setTransparent(false);
        $section->setTemplateBgcolor('#000000');

        $post = new Post();
        $post->setName($this->getUppercaseSiteName());
        $post->setSection($section);
        $post->setPosition(1);
        $post->setActive(true);
        $post->setLocale($locale);
        $post->setContent($html);

        $menu->addSection($section);
        $section->addPost($post);

        $this->entityManager->persist($section);
        $this->entityManager->persist($post);

        $this->createContactMenu($locale);
        $this->createLegalPages($locale, $libreTemplate);
        $this->createFooterLegalLinks($locale);

        $this->entityManager->flush();

        return [
            'created' => true,
            'skipped_reason' => null,
            'menu_id' => $menu->getId(),
        ];
    }

    private function applyWelcomeColors(): void
    {
        foreach (self::WELCOME_CONFIG_VALUES as $code => $value) {
            $config = $this->configRepository->findOneBy(['code' => $code]);
            if (!$config instanceof Config) {
                continue;
            }

            $config->setValue($value);
        }
    }

    private function createContactMenu(string $locale): void
    {
        $menu = new Menu();
        $menu->setName(self::CONTACT_MENU_NAME);
        $menu->setCode(self::CONTACT_MENU_SLUG);
        $menu->setLocale($locale);
        $menu->setActive(true);
        $menu->setReferenceName(self::CONTACT_MENU_SLUG);

        $this->menuManager->create($menu);
        $this->forceMenuSlug($menu, self::CONTACT_MENU_SLUG);

        foreach ($menu->getSections() as $section) {
            $section->setActive(true);
            $section->setTransparent(false);
            $section->setTemplateBgcolor('#000000');
            $section->setTemplateWidth(12);
        }
    }

    private function createLegalPages(string $locale, Template $libreTemplate): void
    {
        foreach (self::LEGAL_PAGES as $page) {
            $menu = new Menu();
            $menu->setName($page['name']);
            $menu->setCode($page['slug']);
            $menu->setLocale($locale);
            $menu->setActive(false);
            $menu->setReferenceName($page['reference']);

            $this->menuManager->create($menu);
            $this->forceMenuSlug($menu, $page['slug']);

            $section = new Section();
            $section->setMenu($menu);
            $section->setTemplate($libreTemplate);
            $section->setPosition(1);
            $section->setTemplateWidth(10);
            $section->setActive(true);
            $section->setTransparent(false);
            $section->setTemplateBgcolor('#000000');

            $post = new Post();
            $post->setName($page['name']);
            $post->setSection($section);
            $post->setPosition(1);
            $post->setActive(true);
            $post->setLocale($locale);
            $post->setContent($this->loadTemplateHtml($page['template']));

            $menu->addSection($section);
            $section->addPost($post);

            $this->entityManager->persist($section);
            $this->entityManager->persist($post);
        }
    }

    private function createFooterLegalLinks(string $locale): void
    {
        if ($this->sectionRepository->findOneFooterByLocaleAndReference($locale, self::FOOTER_REFERENCE) !== null) {
            return;
        }

        $footerTemplate = $this->templateRepository->findOneBy(['code' => FooterSectionService::TEMPLATE_CODE]);
        if (!$footerTemplate instanceof Template) {
            throw new \RuntimeException('Gabarit « footer_template » introuvable. Exécutez d’abord app:init-hermes.');
        }

        $section = new Section();
        $section->setMenu(null);
        $section->setLocale($locale);
        $section->setReferenceName(self::FOOTER_REFERENCE);
        $section->setTemplate($footerTemplate);
        $section->setTemplateWidth(12);
        $section->setPosition($this->sectionRepository->getNextFooterPosition($locale));
        $section->setActive(true);
        $section->setTransparent(false);
        $section->setTemplateBgcolor('#000000');

        $post = new Post();
        $post->setName('Pages légales');
        $post->setSection($section);
        $post->setPosition(1);
        $post->setActive(true);
        $post->setLocale($locale);
        $post->setContent($this->buildFooterLinksHtml($locale));

        $section->addPost($post);

        $this->entityManager->persist($section);
        $this->entityManager->persist($post);
    }

    private function buildWelcomeHtml(): string
    {
        return $this->loadTemplateHtml(self::TEMPLATE_FILE);
    }

    private function loadTemplateHtml(string $relativePath): string
    {
        $path = rtrim($this->projectDir, '/') . '/' . $relativePath;
        if (!is_readable($path)) {
            throw new \RuntimeException(sprintf('Modèle introuvable : %s', $relativePath));
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException(sprintf('Impossible de lire le modèle : %s', $relativePath));
        }

        return str_replace(self::PLACEHOLDER, $this->getEscapedSiteName(), $raw);
    }

    private function buildFooterLinksHtml(string $locale): string
    {
        $localePath = rawurlencode($locale);

        return sprintf(
            <<<'HTML'
<div class="container-fluid col-12 col-sm-6 mx-auto mt-3 text-white">
    <hr style="background-color:#111111;">
    <div class="row">
        <div class="col-lg-4">
            <h5>%2$s</h5>
            <p>Votre site Web, vous le voyez comment?</p>
        </div>
        <div class="col-lg-4">
            <h6>Informations</h6>
            <ul class="list-unstyled">
                <li><a class="text-decoration-none text-white" href="/%1$s/mentions-legales">Mentions Légales</a></li>
                <li><a class="text-decoration-none text-white" href="/%1$s/confidentialite">Confidentialite</a></li>
                <li><a class="text-decoration-none text-white" href="/%1$s/cgu-cgv">CGU-CGV</a></li>
                <li><a class="text-decoration-none text-white" href="/%1$s/contact">Contact</a></li>
            </ul>
        </div>
        <div class="col-lg-4">
            <h6>Coordonnées</h6>
            <p class="mb-1">📍 Paris, France</p>
            <p><i class="fa-solid fa-phone">&nbsp;</i> 06 11 22 33 44</p>
            <p>✉ contact@atlas-services.fr</p>
        </div>
    </div>
</div>
HTML,
            $localePath,
            $this->getEscapedSiteName(),
        );
    }

    private function getEscapedSiteName(): string
    {
        return htmlspecialchars($this->getSiteName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function getUppercaseSiteName(): string
    {
        return mb_strtoupper($this->getSiteName(), 'UTF-8');
    }

    private function getSiteName(): string
    {
        return trim($this->appName) !== '' ? trim($this->appName) : 'monsite';
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
