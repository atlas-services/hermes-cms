<?php

namespace App\Controller\Admin;

use App\Entity\Menu;
use App\Form\MenuType;
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
    #[Route(name: 'menu_index', methods: ['GET'])]
    public function index(MenuTreeBuilder $menuTreeBuilder): Response
    {

        return $this->render('admin/menu/index.html.twig', [
            'menus' => $menuTreeBuilder->buildTree(), // 🌳 arbre complet
        ]);
    }

    #[Route('/new', name: 'menu_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        MenuManager $menuManager,
        MenuRepository $menuRepository
    ): Response {
        $menu = new Menu();

        // 🔗 Gestion du parent via ?parent=ID
        if ($parentId = $request->query->get('parent')) {
            $parent = $menuRepository->find($parentId);

            if ($parent) {
                $menu->setParent($parent);
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

                return $this->redirectToRoute('menu_index');

            } catch (MaxDepthExceededException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/menu/new.html.twig', [
            'menu' => $menu,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'menu_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Menu $menu,
        MenuManager $menuManager
    ): Response {
        $form = $this->createForm(MenuType::class, $menu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // 👉 Si parent modifié → vérifier profondeur
            if ($menu->getParent()) {
                try {
                    $menuManager->assertCanAddChild($menu->getParent());
                } catch (MaxDepthExceededException $e) {
                    $this->addFlash('error', $e->getMessage());

                    return $this->redirectToRoute('menu_index');
                }
            }

            $menuManager->create($menu); // 👉 garantit position cohérente

            $this->addFlash('info', sprintf(
                'Menu "%s" mis à jour !',
                $menu->getName()
            ));

            return $this->redirectToRoute('menu_index');
        }

        return $this->render('admin/menu/edit.html.twig', [
            'menu' => $menu,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'menu_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        Request $request,
        Menu $menu,
        EntityManagerInterface $em
    ): Response {
        if ($this->isCsrfTokenValid('delete_' . (string) $menu->getId(), $request->request->get('_token'))) {

            $em->remove($menu);
            $em->flush();

            $this->addFlash('info', sprintf(
                'Menu "%s" supprimé !',
                $menu->getName()
            ));
        }

        return $this->redirectToRoute('menu_index');
    }

    #[Route('/{id<\d+>}', name: 'menu_show', methods: ['GET'], priority: 10)]
    public function show(Menu $menu): Response
    {
        return $this->render('admin/menu/show.html.twig', [
            'menu' => $menu,
        ]);
    }


}
