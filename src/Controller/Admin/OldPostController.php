<?php

namespace App\Controller\Admin;

use App\Entity\Post;
use App\Form\PostType;
use App\Service\PostService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/admin/post', defaults: ['_locale' => 'fr'], requirements: ['_locale' => 'fr|en'],)]
final class OldPostController extends AbstractController
{

    #[Route(name: 'post_index', methods: ['GET'])]
    public function index(PostService $postService): Response
    {

        $posts = $postService->getPosts();

        return $this->render('admin/post/index.html.twig', [
            'posts' => $posts,
        ]);
    }

    #[Route('/new', name: 'post_new', methods: ['GET', 'POST'])]
    public function new(Request $request, PostService $postService): Response
    {
        $post = new Post();
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $postService->createPost($post);
             $this->addFlash('info', sprintf('Post "%s" created!', $post->getName()));
            return $this->redirectToRoute('post_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/post/new.html.twig', [
            'post' => $post,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'post_show', methods: ['GET'])]
    public function show(Post $post): Response
    {
        return $this->render('admin/post/show.html.twig', [
            'post' => $post,
        ]);
    }

    #[Route('/{id}/edit', name: 'post_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Post $post, EntityManagerInterface $entityManager): Response
    {
        // $form = $this->createForm(postType::class, $post);
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
             $this->addFlash('info', sprintf('Post "%s" updated!', $post->getName()));
            return $this->redirectToRoute('post_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/post/edit.html.twig', [
            'post' => $post,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'post_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Post $post, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$post->getId(), $request->request->get('_token'))) {
            $entityManager->remove($post);
            $entityManager->flush();
            $this->addFlash('info', sprintf('Post "%s" deleted!', $post->getName()));
        }

        return $this->redirectToRoute('post_index', [], Response::HTTP_SEE_OTHER);
    }

}
