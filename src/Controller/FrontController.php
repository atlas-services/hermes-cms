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
