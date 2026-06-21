<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\Trait\AdminMenuLocaleFilterTrait;
use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use App\Form\PostType;
use App\Repository\MenuRepository;
use App\Repository\TemplateRepository;
use App\Service\AdminMediaStorage;
use App\Service\AdminMediaUploadLimits;
use App\Service\AppLocaleService;
use App\Service\MenuTreeBuilder;
use App\Service\PostBulkImagesFromMediaService;
use App\Service\PostService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/{_locale}/admin/post')]
class PostController extends AbstractController
{
    use AdminMenuLocaleFilterTrait;

    public function __construct(
        private PostService $postService,
        private MenuRepository $menuRepository,
        private EntityManagerInterface $entityManager,
        private TemplateRepository $templateRepository,
        private MenuTreeBuilder $menuTreeBuilder,
        private TranslatorInterface $translator,
        AppLocaleService $appLocaleService,
    ) {
        $this->appLocaleService = $appLocaleService;
    }

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

        $form = $this->createForm(PostType::class, $post, [
            'menu' => $menu,
            'liste_bulk_import_save' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $template = $form->has('template') ? $form->get('template')->getData() : null;
            $isListeTemplate = $template instanceof Template
                && strtolower(trim((string) $template->getType())) === PostType::TEMPLATE_TYPE_LISTE;
            $saveAndImportButton = $form->get('saveAndImportImages');
            $saveAndImportClicked = $saveAndImportButton instanceof SubmitButton
                && $saveAndImportButton->isClicked();

            try {
                if ($isListeTemplate && $saveAndImportClicked) {
                    $section = $this->postService->createSectionFromMenu($menu, $template);
                    $this->addFlash('success', $this->translator->trans('form.label.post_saved', [], 'messages'));
                    $this->addFlash('info', $this->translator->trans('admin.post_bulk.after_create_redirect_hint'));

                    return $this->redirectToRoute('post_bulk_import_media_images', [
                        '_locale' => $request->getLocale(),
                        'id' => $section->getId(),
                    ]);
                }

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
            'section' => null,
            'isFooterSection' => false,
            'post_template_field_toggle' => true,
            'post_template_types' => $this->buildPostTemplateTypeMeta(),
            'liste_import_section' => null,
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
        $isFooter = $section->isFooterSection();

        if (!$isFooter && !$menu) {
            throw $this->createNotFoundException('Menu introuvable pour cette section');
        }

        $post = new Post();
        $post->setSection($section);

        $sectionTpl = $section->getTemplate();
        $formOptions = [
            'menu' => $menu,
            'selected_section' => $section,
            'template_type' => $isFooter ? PostType::TEMPLATE_TYPE_LIBRE : $sectionTpl?->getType(),
        ];
        if ($sectionTpl instanceof Template
            && strtolower(trim((string) $sectionTpl->getType())) === PostType::TEMPLATE_TYPE_LISTE) {
            // Même parcours que la création depuis menu : image non obligatoire si « import médias ».
            $formOptions['liste_bulk_import_save'] = true;
        }

        $form = $this->createForm(PostType::class, $post, $formOptions);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $saveAndImportButton = $form->has('saveAndImportImages') ? $form->get('saveAndImportImages') : null;
                $isListeSection = $sectionTpl instanceof Template
                    && strtolower(trim((string) $sectionTpl->getType())) === PostType::TEMPLATE_TYPE_LISTE
                    && $saveAndImportButton instanceof SubmitButton;
                $saveAndImportClicked = $isListeSection
                    ? $saveAndImportButton instanceof SubmitButton && $saveAndImportButton->isClicked()
                    : false;

                if ($saveAndImportClicked) {
                    $this->addFlash('info', $this->translator->trans('admin.post_bulk.after_create_redirect_hint'));

                    return $this->redirectToRoute('post_bulk_import_media_images', [
                        '_locale' => $request->getLocale(),
                        'id' => $section->getId(),
                    ]);
                }

                $this->postService->create($post, $section);
                $this->addFlash('success', $this->translator->trans('form.label.post_saved', [], 'messages'));

                return $this->redirectToRoute('post_edit', [
                    'id' => $post->getId(),
                ]);
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        $isListeSection = $sectionTpl instanceof Template
            && strtolower(trim((string) $sectionTpl->getType())) === PostType::TEMPLATE_TYPE_LISTE;

        return $this->render('admin/post/new.html.twig', [
            'form' => $form->createView(),
            'menu' => $menu,
            'section' => $section,
            'isFooterSection' => $isFooter,
            'liste_import_section' => $isListeSection ? $section : null,
        ]);
    }

    #[Route('/section/{id}/import-images-from-media', name: 'post_bulk_import_media_images', methods: ['GET', 'POST'])]
    public function bulkImportMediaImages(
        Request $request,
        Section $section,
        AdminMediaStorage $mediaStorage,
        PostBulkImagesFromMediaService $bulkImagesFromMediaService,
    ): Response {
        $menu = $section->getMenu();
        if ($menu === null) {
            throw $this->createNotFoundException('Menu introuvable pour cette section');
        }

        $tpl = $section->getTemplate();
        if ($tpl === null || strtolower(trim((string) $tpl->getType())) !== PostType::TEMPLATE_TYPE_LISTE) {
            $this->addFlash('warning', $this->translator->trans('admin.post_bulk.section_not_liste'));

            return $this->redirectToRoute('post_index', $this->postIndexParams($request, $menu));
        }

        $redirectBack = fn (): Response => $this->redirectToRoute('post_index', $this->postIndexParams($request, $menu));
        $rootFs = $mediaStorage->getRootFs();
        if (!is_dir($rootFs) && !@mkdir($rootFs, 0775, true) && !is_dir($rootFs)) {
            $this->addFlash('danger', $this->translator->trans('admin.post_bulk.upload_dir_missing'));

            return $redirectBack();
        }

        if ($request->isMethod('POST')) {
            $tokenId = 'post_bulk_import_images_' . $section->getId();
            if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
                $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.invalid_csrf'));

                return $redirectBack();
            }

            $mediaPath = (string) $request->request->get('media_path', '');
            try {
                $count = $bulkImagesFromMediaService->importFlatImageDirectory(
                    $section,
                    $mediaPath,
                    $request->getLocale(),
                );
                $this->addFlash('success', $this->translator->trans('admin.post_bulk.flash_imported', ['%count%' => $count]));
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('danger', $this->translator->trans('admin.post_bulk.duplicate_name'));
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            } catch (\Throwable) {
                $this->addFlash('danger', $this->translator->trans('admin.post_bulk.import_error'));
            }

            return $redirectBack();
        }

        $currentPath = '';
        try {
            $currentPath = $mediaStorage->normalizeRelativePath((string) $request->query->get('path', ''));
        } catch (\InvalidArgumentException) {
            $this->addFlash('danger', $this->translator->trans('admin.post_bulk.invalid_path'));

            return $this->redirectToRoute('post_bulk_import_media_images', [
                '_locale' => $request->getLocale(),
                'id' => $section->getId(),
            ]);
        }

        $listing = ['directories' => [], 'files' => []];
        $uploadDirReadable = is_dir($rootFs) && is_readable($rootFs);
        if ($uploadDirReadable) {
            try {
                $listing = $mediaStorage->listDirectory($currentPath);
            } catch (\Throwable) {
                $this->addFlash('danger', $this->translator->trans('admin.post_bulk.list_failed'));
                if ($currentPath === '') {
                    return $redirectBack();
                }

                $parent = $mediaStorage->parentRelativePath($currentPath);
                $params = ['_locale' => $request->getLocale(), 'id' => $section->getId()];
                if ($parent !== '' && $parent !== $currentPath) {
                    $params['path'] = $parent;
                }

                return $this->redirectToRoute('post_bulk_import_media_images', $params);
            }
        } else {
            $this->addFlash('danger', $this->translator->trans('admin.post_bulk.upload_dir_missing'));
        }

        $imageFiles = $uploadDirReadable
            ? $bulkImagesFromMediaService->listFlatImageFilesInDirectory($currentPath)
            : [];

        return $this->render('admin/post/bulk_import_media_images.html.twig', [
            'section' => $section,
            'menu' => $menu,
            'page_menu_id' => $menu->getId(),
            'current_path' => $currentPath,
            'directories' => $listing['directories'],
            'image_files' => $imageFiles,
            'web_base' => $mediaStorage->getWebBasePath(),
            'parent_path' => $mediaStorage->parentRelativePath($currentPath),
            'upload_dir_readable' => $uploadDirReadable,
            'upload_enabled' => $uploadDirReadable,
            'max_upload_bytes' => AdminMediaUploadLimits::effectiveMaxBytes($this->getParameter('app.upload_max_size')),
            'allowed_mime_types' => AdminMediaUploadLimits::IMAGE_MIME_TYPES,
        ]);
    }

    #[Route('/section/{id}/delete', name: 'section_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteSection(Request $request, Section $section): Response
    {
        $back = $this->adminListRouteForSection($section);

        if (!$this->isCsrfTokenValid('delete_section_' . $section->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.invalid_csrf'));

            return $this->redirectToRoute($back['route'], $back['params']);
        }

        $this->postService->deleteSection($section);
        $this->addFlash('success', $this->translator->trans('admin.section.flash_section_deleted'));

        return $this->redirectToRoute($back['route'], $back['params']);
    }

    #[Route('/section/{id}/clear-liste-images', name: 'section_clear_liste_images', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function clearListeSectionImages(Request $request, Section $section): Response
    {
        $back = $this->adminListRouteForSection($section);

        if (!$this->isCsrfTokenValid('delete_liste_posts_' . $section->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.invalid_csrf'));

            return $this->redirectToRoute($back['route'], $back['params']);
        }

        try {
            $n = $this->postService->deleteAllPostsInListeSection($section);
            if ($n === 0) {
                $this->addFlash('info', $this->translator->trans('admin.section.flash_liste_posts_none'));
            } else {
                $this->addFlash('success', $this->translator->trans('admin.section.flash_liste_posts_deleted', ['%count%' => $n]));
            }
        } catch (\DomainException) {
            $this->addFlash('warning', $this->translator->trans('admin.section.flash_not_liste'));
        }

        return $this->redirectToRoute($back['route'], $back['params']);
    }

    #[Route(name: 'post_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $menuLocale = $this->resolveMenuFilterLocale($request);
        $pages = $this->menuRepository->findPages($menuLocale);
        $pageChoices = $this->menuTreeBuilder->orderPagesByTree($pages, $menuLocale);
        $pageId = $request->query->getInt('page');
        $selectedPage = null;

        if ($pageId > 0) {
            foreach ($pageChoices as $choice) {
                if ($choice['menu']->getId() === $pageId) {
                    $selectedPage = $choice['menu'];
                    break;
                }
            }
        }

        if ($selectedPage === null && $pageChoices !== []) {
            $selectedPage = $pageChoices[0]['menu'];
        }

        return $this->render('admin/post/index.html.twig', [
            'pageChoices' => $pageChoices,
            'selectedPage' => $selectedPage,
            'menu_locale' => $menuLocale,
            'menu_locales' => $this->appLocaleService->getContentLocales(),
            'sectionModaleChoices' => $this->templateRepository->getModaleChoicesForSectionAdmin(),
            'sectionListeTemplateChoices' => $this->buildSectionListeTemplateChoicesBySectionId($selectedPage),
            'gsapImageRevealEffectChoices' => \App\Enum\GsapImageRevealEffect::choicesForAdmin(),
        ]);
    }

    // -------------------------
    // UPDATE
    // -------------------------
    #[Route('/{id}/edit', name: 'post_edit')]
    public function edit(Request $request, Post $post): Response
    {
        $section = $post->getSection();
        $sectionTpl = $section?->getTemplate();
        $isListeSection = $sectionTpl instanceof Template
            && strtolower(trim((string) $sectionTpl->getType())) === PostType::TEMPLATE_TYPE_LISTE;

        $form = $this->createForm(PostType::class, $post, [
            'template_type' => $sectionTpl?->getType(),
            'post_edit_mode' => true,
            'liste_bulk_import_save' => $isListeSection,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $section = $form->has('section') ? $form->get('section')->getData() : $post->getSection();

            $this->postService->update($post, $section instanceof Section ? $section : null);
            $this->addFlash('success', $this->translator->trans('form.label.post_saved', [], 'messages'));

            return $this->redirectToRoute('post_edit', [
                'id' => $post->getId(),
            ]);
        }

        return $this->render('admin/post/form.html.twig', [
            'form' => $form->createView(),
            'post' => $post,
            'liste_import_section' => $isListeSection ? $section : null,
        ]);
    }

    // -------------------------
    // DELETE
    // -------------------------
    #[Route('/{id}/delete', name: 'post_delete', methods: ['POST'])]
    public function delete(Request $request, Post $post): Response
    {
        if (!$this->isCsrfTokenValid('delete_' . (string) $post->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('post_index', $this->postIndexParams($request));
        }

        $section = $post->getSection();
        if ($section === null) {
            return $this->redirectToRoute('post_index', $this->postIndexParams($request));
        }

        $back = $this->adminListRouteForSection($section);
        $this->postService->delete($post);

        return $this->redirectToRoute($back['route'], $back['params']);
    }

    /**
     * @return array{route: string, params: array<string, int|string>}
     */
    private function adminListRouteForSection(Section $section): array
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $locale = $request?->getLocale() ?? 'fr';

        if ($section->isFooterSection()) {
            return [
                'route' => 'admin_footer_sections_index',
                'params' => ['_locale' => $locale],
            ];
        }

        $menu = $section->getMenu();
        if ($menu === null || $menu->getId() === null) {
            return [
                'route' => 'post_index',
                'params' => $request instanceof Request
                    ? $this->postIndexParams($request)
                    : ['_locale' => $locale, 'menu_locale' => $this->appLocaleService->getDefaultLocale()],
            ];
        }

        return [
            'route' => 'post_index',
            'params' => $request instanceof Request
                ? $this->postIndexParams($request, $menu)
                : [
                    '_locale' => $locale,
                    'menu_locale' => $menu->getLocale() ?? $this->appLocaleService->getDefaultLocale(),
                    'page' => $menu->getId(),
                ],
        ];
    }

    /**
     * Gabarits « liste » proposés pour le sélecteur admin (même liste pour chaque section de type liste).
     *
     * @return array<int, list<Template>>
     */
    private function buildSectionListeTemplateChoicesBySectionId(?Menu $page): array
    {
        if (!$page instanceof Menu) {
            return [];
        }

        $listeTemplates = $this->templateRepository->findActiveByTrimmedType('liste');
        $out = [];
        foreach ($page->getSections() as $section) {
            $sid = $section->getId();
            if ($sid === null) {
                continue;
            }
            $t = $section->getTemplate();
            $out[$sid] = ($t instanceof Template && trim((string) $t->getType()) === 'liste')
                ? $listeTemplates
                : [];
        }

        return $out;
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
