<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Form\PostType;
use App\Repository\MenuRepository;
use App\Repository\TemplateRepository;
use App\Service\PostService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/{_locale}/admin/post')]
class PostController extends AbstractController
{
    public function __construct(
        private PostService $postService,
        private MenuRepository $menuRepository,
        private EntityManagerInterface $entityManager,
        private TemplateRepository $templateRepository,
        private TranslatorInterface $translator,
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

        $form = $this->createForm(PostType::class, $post, ['menu' => $menu]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $template = $form->has('template') ? $form->get('template')->getData() : null;

            try {
                $this->postService->createFromMenu($post, $menu, $template);
                $this->addFlash('success', $this->translator->trans('form.label.post_saved', [], 'messages'));

                return $this->redirectToRoute('post_edit', [
                    'id' => $post->getId(),
                ]);
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('admin/post/new.html.twig', [
            'form' => $form->createView(),
            'menu' => $menu,
            'post_template_field_toggle' => true,
            'post_template_types' => $this->buildPostTemplateTypeMeta(),
        ]);
    }

    #[Route('/section/{id}/new', name: 'post_new_section')]
    public function createForSection(Request $request, int $id): Response
    {
        $section = $this->entityManager->getRepository(Section::class)->find($id);

        if (!$section) {
            throw $this->createNotFoundException('Section introuvable');
        }

        $menu = $section->getMenu();

        if (!$menu) {
            throw $this->createNotFoundException('Menu introuvable pour cette section');
        }

        $post = new Post();
        $post->setSection($section);

        $form = $this->createForm(PostType::class, $post, [
            'menu' => $menu,
            'selected_section' => $section,
            'template_type' => $section->getTemplate()?->getType(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->postService->create($post, $section);
                $this->addFlash('success', $this->translator->trans('form.label.post_saved', [], 'messages'));

                return $this->redirectToRoute('post_edit', [
                    'id' => $post->getId(),
                ]);
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('admin/post/new.html.twig', [
            'form' => $form->createView(),
            'menu' => $menu,
            'section' => $section,
        ]);
    }

    #[Route(name: 'post_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $pages = $this->menuRepository->findPages();
        $pageId = $request->query->getInt('page');
        $selectedPage = null;

        if ($pageId > 0) {
            $selectedPage = $this->menuRepository->find($pageId);
        }

        if ($selectedPage === null && count($pages) > 0) {
            $selectedPage = $pages[0];
        }

        return $this->render('admin/post/index.html.twig', [
            'pages' => $pages,
            'selectedPage' => $selectedPage,
        ]);
    }

    // -------------------------
    // UPDATE
    // -------------------------
    #[Route('/{id}/edit', name: 'post_edit')]
    public function edit(Request $request, Post $post): Response
    {
        $form = $this->createForm(PostType::class, $post, [
            'template_type' => $post->getSection()?->getTemplate()?->getType(),
            'post_edit_mode' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $section = $form->has('section') ? $form->get('section')->getData() : $post?->getSection();

            $this->postService->update($post, $section instanceof Section ? $section : null);
            $this->addFlash('success', $this->translator->trans('form.label.post_saved', [], 'messages'));

            return $this->redirectToRoute('post_edit', [
                'id' => $post->getId(),
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
    public function delete(Request $request, Post $post): Response
    {
        if (!$this->isCsrfTokenValid('delete_' . (string) $post->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('post_index');
        }

        $menuId = $post->getSection()->getMenu()->getId();

        $this->postService->delete($post);

        return $this->redirectToRoute('menu_edit', [
            'id' => $menuId
        ]);
    }

    /**
     * Métadonnées pour le JS (création post depuis menu) : type logique par id de template.
     *
     * @return array{templates: array<string, string|null>}
     */
    private function buildPostTemplateTypeMeta(): array
    {
        $templates = [];
        foreach ($this->templateRepository->getInitTemplates() as $template) {
            $templates[(string) $template->getId()] = $template->getType();
        }

        return [
            'templates' => $templates,
        ];
    }
}
