<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Menu;
use App\Service\FrontMenuService;
use App\Service\MenuTreeBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FrontController extends AbstractController
{
    #[Route('/', name: 'front_root')]
    #[Route('/{_locale}/', name: 'front_home', requirements: ['_locale' => 'fr|en'], defaults: ['_locale' => 'fr'])]
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

        return $this->render('front/index.html.twig', [
            'menu' => $firstPage,
            'sections' => $firstPage->getSections(),
            'menuTree' => $menuTreeBuilder->buildTree(),
        ]);
    }

    #[Route('/{_locale}/contact', name: 'front_contact', requirements: ['_locale' => 'fr|en'], defaults: ['_locale' => 'fr'])]
    public function contact(): Response
    {
        return $this->render('front/contact.html.twig');
    }

    #[Route('/{_locale}/{slugs}', name: 'front_menu', requirements: ['_locale' => 'fr|en', 'slugs' => '(?!(contact|login|logout|admin)(/|$)).+'], defaults: ['_locale' => 'fr'])]
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

        $sections = $menu->getSections();
        $menuTree = $menuTreeBuilder->buildTree();

        return $this->render('front/index.html.twig', [
            'menu' => $menu,
            'sections' => $sections,
            'menuTree' => $menuTree,
        ]);
    }
}
