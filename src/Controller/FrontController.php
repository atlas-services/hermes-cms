<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Menu;
use App\Enum\FormTemplateKind;
use App\Form\Front\ContactFormType;
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
            'sectionsForFront' => $frontMenuService->getVisibleFrontSections($firstPage),
            'menuTree' => $menuTreeBuilder->buildTree(true),
        ]);
    }

    #[Route('/{_locale}/contact', name: 'front_contact', requirements: ['_locale' => 'fr|en'], defaults: ['_locale' => 'fr'], methods: ['GET', 'POST'])]
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
            ? $frontMenuService->getVisibleFrontSections($menu)
            : [];

        return $this->render('front/contact.html.twig', [
            'menu' => $menu,
            'menuTree' => $menuTreeBuilder->buildTree(true),
            'sectionsForFront' => $sectionsForFront,
        ]);
    }

    #[Route('/{_locale}/{slugs}', name: 'front_menu', requirements: ['_locale' => 'fr|en', 'slugs' => '(?!(contact|form|login|logout|admin|forgotten_password|re-init-password|reset_password|sitemap\\.xml)(/|$)).+'], defaults: ['_locale' => 'fr'])]
    public function menu(string $slugs, Request $request, FrontMenuService $frontMenuService, MenuTreeBuilder $menuTreeBuilder): Response
    {
        $slugParts = array_filter(explode('/', $slugs));

        if (count($slugParts) > Menu::MAX_DEPTH) {
            throw $this->createNotFoundException('Menu depth exceeded');
        }

        $locale = $request->getLocale();
        $menu = $frontMenuService->findMenuBySlugs($slugParts, $locale);

        if (!$menu || !$menu->isPage() || !$frontMenuService->isMenuHierarchyFullyActive($menu)) {
            throw $this->createNotFoundException('Menu not found');
        }

        $sectionsForFront = $frontMenuService->getVisibleFrontSections($menu);
        $menuTree = $menuTreeBuilder->buildTree(true);

        return $this->render('front/index.html.twig', [
            'menu' => $menu,
            'sectionsForFront' => $sectionsForFront,
            'menuTree' => $menuTree,
        ]);
    }
}
