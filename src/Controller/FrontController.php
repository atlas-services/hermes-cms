<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Menu;
use App\Entity\Post;
use App\Enum\FormTemplateKind;
use App\Form\Front\ContactFormType;
use App\Repository\PostRepository;
use App\Service\FrontFormSubmissionHandler;
use App\Service\FrontMenuService;
use App\Service\MenuTreeBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FrontController extends AbstractController
{
    #[Route('/', name: 'front_root')]
    #[Route('/{_locale}/', name: 'front_home', requirements: ['_locale' => '[a-z]{2,3}'], defaults: ['_locale' => 'fr'])]
    public function home(Request $request, FrontMenuService $frontMenuService, MenuTreeBuilder $menuTreeBuilder): Response
    {
        $preferredLocales = [];
        $routeLocale = $request->attributes->get('_locale');
        if (is_string($routeLocale) && $routeLocale !== '') {
            $preferredLocales[] = $routeLocale;
        }
        $preferredLocales[] = $request->getLocale();
        $preferredLocales[] = 'fr';
        $preferredLocales[] = 'en';
        $preferredLocales = array_values(array_unique(array_filter($preferredLocales)));

        $firstPage = null;
        foreach ($preferredLocales as $locale) {
            $firstPage = $frontMenuService->findFirstAccessiblePage($locale);
            if ($firstPage !== null) {
                break;
            }
        }
        $firstPage ??= $frontMenuService->findFirstAccessiblePageAnyLocale();
        if ($firstPage === null) {
            throw $this->createNotFoundException('No accessible page found for locale');
        }

        $request->setLocale($firstPage->getLocale());
        $request->attributes->set('_locale', $firstPage->getLocale());

        $locale = $firstPage->getLocale() ?? 'fr';

        return $this->render('front/index.html.twig', [
            'menu' => $firstPage,
            'sectionsForFront' => $frontMenuService->getVisibleFrontSections($firstPage, $locale),
            'menuTree' => $menuTreeBuilder->buildTree(true, $locale),
        ]);
    }

    #[Route('/{_locale}/contact', name: 'front_contact', requirements: ['_locale' => '[a-z]{2,3}'], defaults: ['_locale' => 'fr'], methods: ['GET', 'POST'])]
    public function contact(
        Request $request,
        FrontMenuService $frontMenuService,
        MenuTreeBuilder $menuTreeBuilder,
        FrontFormSubmissionHandler $formHandler,
    ): Response {
        $response = $formHandler->handle(
            $request,
            FormTemplateKind::Contact,
            ContactFormType::class,
            'front_contact',
            ['input_class' => 'form-control'],
        );
        if ($response !== null) {
            return $response;
        }

        $locale = $request->getLocale();
        $menu = $frontMenuService->findContactPage($locale);
        $sectionsForFront = $menu !== null
            ? $frontMenuService->getVisibleFrontSections($menu, $locale)
            : [];

        return $this->render('front/contact.html.twig', [
            'menu' => $menu,
            'menuTree' => $menuTreeBuilder->buildTree(true, $locale),
            'sectionsForFront' => $sectionsForFront,
        ]);
    }

    #[Route('/{_locale}/search', name: 'search_content', requirements: ['_locale' => '[a-z]{2,3}'], defaults: ['_locale' => 'fr'], methods: ['GET'])]
    public function search(
        Request $request,
        PostRepository $postRepository,
        FrontMenuService $frontMenuService,
        MenuTreeBuilder $menuTreeBuilder,
    ): Response {
        $locale = $request->getLocale();
        $query = trim((string) $request->query->get('q', ''));
        $posts = mb_strlen($query) >= 2
            ? $postRepository->findVisibleBySearchTerm($query, $locale)
            : [];
        $currentMenu = $frontMenuService->findFirstAccessiblePage($locale);

        return $this->render('front/search.html.twig', [
            'menu' => $currentMenu,
            'menuTree' => $menuTreeBuilder->buildTree(true, $locale),
            'query' => $query,
            'results' => array_map(
                fn (Post $post): array => $this->buildSearchResult($post, $query, $locale),
                $posts,
            ),
        ]);
    }

    #[Route('/{_locale}/{slugs}', name: 'front_menu', requirements: ['_locale' => '[a-z]{2,3}', 'slugs' => '(?!(contact|search|form|login|logout|admin|forgotten_password|re-init-password|reset_password|sitemap\\.xml)(/|$)).+'], defaults: ['_locale' => 'fr'])]
    public function menu(string $slugs, Request $request, FrontMenuService $frontMenuService, MenuTreeBuilder $menuTreeBuilder): Response
    {
        $slugParts = array_filter(explode('/', $slugs));

        if (count($slugParts) > Menu::MAX_DEPTH) {
            throw $this->createNotFoundException('Menu depth exceeded');
        }

        $locale = $request->getLocale();
        $menu = $frontMenuService->findMenuBySlugs($slugParts, $locale);

        if (!$menu || !$menu->isPage()) {
            throw $this->createNotFoundException('Menu not found');
        }

        $sectionsForFront = $frontMenuService->getVisibleFrontSections($menu, $locale);
        $menuTree = $menuTreeBuilder->buildTree(true, $locale);

        return $this->render('front/index.html.twig', [
            'menu' => $menu,
            'sectionsForFront' => $sectionsForFront,
            'menuTree' => $menuTree,
        ]);
    }

    /**
     * @return array{title: string, excerpt: string, url: string, page: string}
     */
    private function buildSearchResult(Post $post, string $query, string $locale): array
    {
        $section = $post->getSection();
        $menu = $section?->getMenu();
        $pageTitle = $menu?->getName() ?? '';
        $postTitle = trim((string) $post->getName());

        return [
            'title' => $postTitle !== '' ? $postTitle : $pageTitle,
            'excerpt' => $this->buildSearchExcerpt((string) $post->getContent(), $query),
            'url' => $menu instanceof Menu ? $this->generateMenuUrl($menu, $locale) : $this->generateUrl('front_home', ['_locale' => $locale]),
            'page' => $pageTitle,
        ];
    }

    private function buildSearchExcerpt(string $content, string $query): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($text === '') {
            return '';
        }

        $position = $query !== '' ? mb_stripos($text, $query) : false;
        $start = $position === false ? 0 : max(0, $position - 90);
        $excerpt = mb_substr($text, $start, 220);

        return ($start > 0 ? '...' : '') . $excerpt . (mb_strlen($text) > $start + 220 ? '...' : '');
    }

    private function generateMenuUrl(Menu $menu, string $locale): string
    {
        $parts = array_map(
            static fn (Menu $item): string => (string) $item->getSlug(),
            [...$menu->getParents(), $menu],
        );

        return $this->generateUrl('front_menu', [
            '_locale' => $menu->getLocale() ?? $locale,
            'slugs' => implode('/', array_values(array_filter($parts))),
        ]);
    }
}
