<?php

namespace App\Controller\Admin;

use App\Controller\Admin\Trait\AdminMenuLocaleFilterTrait;
use App\Entity\Menu;
use App\Form\MenuType;
use App\Service\AppLocaleService;
use App\Service\MenuManager;
use App\Repository\MenuRepository;
use App\Exception\MaxDepthExceededException;
use App\Service\MenuTreeBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/admin/menu', defaults: ['_locale' => 'fr'], requirements: ['_locale' => 'fr|en'])]
final class MenuController extends AbstractController
{
    use AdminMenuLocaleFilterTrait;

    public function __construct(
        AppLocaleService $appLocaleService,
    ) {
        $this->appLocaleService = $appLocaleService;
    }

    #[Route(name: 'menu_index', methods: ['GET'])]
    public function index(Request $request, MenuTreeBuilder $menuTreeBuilder): Response
    {
        $menuLocale = $this->resolveMenuFilterLocale($request);

        return $this->render('admin/menu/index.html.twig', [
            'menus' => $menuTreeBuilder->buildTree(false, $menuLocale),
            'menu_locale' => $menuLocale,
            'menu_locales' => $this->appLocaleService->getContentLocales(),
            'menu_index_query' => ['menu_locale' => $menuLocale],
        ]);
    }

    #[Route('/new', name: 'menu_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        MenuManager $menuManager,
        MenuRepository $menuRepository
    ): Response {
        $menuLocale = $this->resolveMenuFilterLocale($request);
        $menu = new Menu();
        $menu->setLocale($menuLocale);

        if ($parentId = $request->query->get('parent')) {
            $parent = $menuRepository->find($parentId);

            if ($parent) {
                $menu->setParent($parent);
                $menu->setLocale($parent->getLocale() ?? $menuLocale);
            }
        }

        $form = $this->createForm(MenuType::class, $menu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $menuManager->create($menu);

                $this->addFlash('success', 'menu.created');
                if (!$menu->getSections()->isEmpty()) {
                    $this->addFlash('info', 'menu.contact_section_created');
                }

                return $this->redirectToRoute('menu_index', $this->menuIndexParams($request, $menu->getLocale() ?? $menuLocale));

            } catch (MaxDepthExceededException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/menu/new.html.twig', [
            'menu' => $menu,
            'form' => $form,
            'menu_locale' => $menuLocale,
        ]);
    }

    #[Route('/{id}/edit', name: 'menu_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Menu $menu,
        MenuManager $menuManager
    ): Response {
        $menuLocale = $this->resolveMenuFilterLocale($request);
        $form = $this->createForm(MenuType::class, $menu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($menu->getParent()) {
                try {
                    $menuManager->assertCanAddChild($menu->getParent());
                } catch (MaxDepthExceededException $e) {
                    $this->addFlash('error', $e->getMessage());

                    return $this->redirectToRoute('menu_index', $this->menuIndexParams($request, $menuLocale));
                }
            }

            $menuManager->update($menu);

            $this->addFlash('info', sprintf(
                'Menu "%s" mis à jour !',
                $menu->getName()
            ));

            return $this->redirectToRoute('menu_index', $this->menuIndexParams($request, $menu->getLocale() ?? $menuLocale));
        }

        return $this->render('admin/menu/edit.html.twig', [
            'menu' => $menu,
            'form' => $form,
            'menu_locale' => $menuLocale,
        ]);
    }

    #[Route('/{id}/delete', name: 'menu_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        Request $request,
        Menu $menu,
        EntityManagerInterface $em
    ): Response {
        $requestedLocale = $request->request->getString('menu_locale');
        $menuLocale = $requestedLocale !== ''
            ? $this->appLocaleService->resolveAdminFilterLocale($requestedLocale)
            : ($menu->getLocale() ?? $this->appLocaleService->getDefaultLocale());

        if ($this->isCsrfTokenValid('delete_' . (string) $menu->getId(), $request->request->get('_token'))) {
            $menuName = $menu->getName();
            $em->remove($menu);
            $em->flush();

            $this->addFlash('info', sprintf(
                'Menu « %s » supprimé (sous-menus, sections et posts inclus).',
                $menuName
            ));
        }

        return $this->redirectToRoute('menu_index', $this->menuIndexParams($request, $menuLocale));
    }

    #[Route('/{id<\d+>}', name: 'menu_show', methods: ['GET'], priority: 10)]
    public function show(Request $request, Menu $menu): Response
    {
        return $this->render('admin/menu/show.html.twig', [
            'menu' => $menu,
            'menu_locale' => $this->resolveMenuFilterLocale($request),
        ]);
    }
}
