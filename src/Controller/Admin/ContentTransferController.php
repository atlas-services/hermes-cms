<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\Trait\AdminMenuLocaleFilterTrait;
use App\Entity\Post;
use App\Entity\Section;
use App\Form\Admin\ContentTransferType;
use App\Repository\MenuRepository;
use App\Service\AppLocaleService;
use App\Service\ContentTransfer\ContentTransferService;
use App\Service\ContentTransfer\ContentTransferTargetProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/{_locale}/admin/content-transfer', name: 'admin_content_transfer_', requirements: ['_locale' => 'fr|en'])]
final class ContentTransferController extends AbstractController
{
    use AdminMenuLocaleFilterTrait;

    public function __construct(
        AppLocaleService $appLocaleService,
        private readonly ContentTransferService $transferService,
        private readonly ContentTransferTargetProvider $targetProvider,
        private readonly TranslatorInterface $translator,
    ) {
        $this->appLocaleService = $appLocaleService;
    }

    #[Route('/section/{id}/transfer', name: 'section', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function transferSection(Request $request, Section $section): Response
    {
        if ($section->isFooterSection()) {
            throw $this->createNotFoundException();
        }

        return $this->handleTransfer($request, $section, null, 'section');
    }

    #[Route('/post/{id}/transfer', name: 'post', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function transferPost(Request $request, Post $post): Response
    {
        $section = $post->getSection();
        if ($section === null || $section->isFooterSection()) {
            throw $this->createNotFoundException();
        }

        return $this->handleTransfer($request, $section, $post, 'post');
    }

    private function handleTransfer(
        Request $request,
        Section $sourceSection,
        ?Post $sourcePost,
        string $kind,
    ): Response {
        $form = $this->createForm(ContentTransferType::class, null, [
            'show_section_target' => $kind === 'post',
        ]);
        $form->handleRequest($request);

        $backParams = $this->resolveBackParams($request, $sourceSection);

        if ($form->isSubmitted() && $form->isValid()) {
            $operation = (string) $form->get('operation')->getData();
            $targetMenuId = $request->request->getInt('target_menu_id');
            $targetSectionId = $request->request->get('target_section_id');
            $targetSectionId = $targetSectionId > 0 ? $targetSectionId : null;

            try {
                $targetMenu = $this->transferService->resolveTargetMenu($targetMenuId);

                if ($kind === 'section') {
                    if ($operation === 'copy') {
                        $this->transferService->copySection($sourceSection, $targetMenu);
                        $this->addFlash('success', $this->translator->trans('admin.content_transfer.flash_section_copied'));
                    } else {
                        $this->transferService->moveSection($sourceSection, $targetMenu);
                        $this->addFlash('success', $this->translator->trans('admin.content_transfer.flash_section_moved'));
                    }
                    $backParams['page'] = $targetMenu->getId();
                    $backParams['menu_locale'] = $targetMenu->getLocale() ?? $backParams['menu_locale'];
                } else {
                    if ($sourcePost === null) {
                        throw new \DomainException('Post introuvable.');
                    }
                    if ($operation === 'copy') {
                        $targetSection = $targetSectionId === null
                            ? null
                            : $this->targetProvider->findTargetSection($targetSectionId, $targetMenu);
                        $this->transferService->copyPost($sourcePost, $targetMenu, $targetSection);
                        $this->addFlash('success', $this->translator->trans('admin.content_transfer.flash_post_copied'));
                    } else {
                        if ($targetSectionId === null) {
                            throw new \DomainException('Choisissez une section cible pour le déplacement.');
                        }
                        $targetSection = $this->targetProvider->findTargetSection($targetSectionId, $targetMenu);
                        if ($targetSection === null) {
                            throw new \DomainException('Section cible introuvable.');
                        }
                        $this->transferService->movePost($sourcePost, $targetSection);
                        $this->addFlash('success', $this->translator->trans('admin.content_transfer.flash_post_moved'));
                    }
                    $backParams['page'] = $targetMenu->getId();
                    $backParams['menu_locale'] = $targetMenu->getLocale() ?? $backParams['menu_locale'];
                }
            } catch (\DomainException $e) {
                $this->addFlash('warning', $e->getMessage());
            }

            return $this->redirectToRoute('post_index', $backParams);
        }

        $localeChoices = [];
        foreach ($this->targetProvider->getLocaleChoices() as $loc) {
            $localeChoices[$this->appLocaleService->formatLabel($loc)] = $loc;
        }

        $menuLocale = $sourceSection->getMenu()?->getLocale();
        $defaultLocale = $this->appLocaleService->normalize(
            ($menuLocale !== null && $menuLocale !== '') ? $menuLocale : $this->appLocaleService->getDefaultLocale(),
        );

        return $this->render('admin/content_transfer/transfer.html.twig', [
            'form' => $form,
            'kind' => $kind,
            'sourceSection' => $sourceSection,
            'sourcePost' => $sourcePost,
            'pagesPayload' => $this->targetProvider->getPagesPayload(),
            'localeChoices' => $localeChoices,
            'defaultLocale' => $defaultLocale,
            'backParams' => $backParams,
        ]);
    }

    /**
     * @return array{_locale: string, page?: int, menu_locale: string}
     */
    private function resolveBackParams(Request $request, Section $section): array
    {
        $menu = $section->getMenu();
        $params = [
            '_locale' => $request->getLocale(),
            'menu_locale' => $this->resolveMenuFilterLocale($request),
        ];
        if ($menu?->getId() !== null) {
            $params['page'] = $menu->getId();
        } elseif ($request->query->getInt('page') > 0) {
            $params['page'] = $request->query->getInt('page');
        }

        return $params;
    }
}
