<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Post;
use App\Form\PostType;
use App\Repository\MenuRepository;
use App\Service\PostService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/admin/post')]
class PostController extends AbstractController
{
    public function __construct(
        private PostService $postService,
        private MenuRepository $menuRepository,
    ) {}

    // -------------------------
    // CREATE (via Menu)
    // -------------------------
    #[Route('/menu/{id}/new', name: 'post_new')]
    public function create(Request $request, int $id): Response
    {
        $menu = $this->menuRepository->find($id);

        if (!$menu) {
            throw $this->createNotFoundException('Menu introuvable');
        }

        $post = new Post();

        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $template = $form->get('template')->getData();

            $this->postService->createFromMenu($post, $menu, $template);

            return $this->redirectToRoute('menu_edit', [
                'id' => $menu->getId()
            ]);
        }

        return $this->render('admin/post/new.html.twig', [
            'form' => $form->createView(),
            'menu' => $menu
        ]);
    }

    // -------------------------
    // UPDATE
    // -------------------------
    #[Route('/{id}/edit', name: 'post_edit')]
    public function edit(Request $request, Post $post): Response
    {
        $form = $this->createForm(\App\Form\PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $this->postService->update($post);

            return $this->redirectToRoute('menu_edit', [
                'id' => $post->getSection()->getMenu()->getId()
            ]);
        }

        return $this->render('admin/post/form.html.twig', [
            'form' => $form->createView(),
            'post' => $post
        ]);
    }

    // -------------------------
    // DELETE
    // -------------------------
    #[Route('/{id}/delete', name: 'post_delete', methods: ['POST'])]
    public function delete(Post $post): Response
    {
        $menuId = $post->getSection()->getMenu()->getId();

        $this->postService->delete($post);

        return $this->redirectToRoute('menu_edit', [
            'id' => $menuId
        ]);
    }
}
