<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\SectionRepository;
use App\Service\AppLocaleService;
use App\Service\TopbarSectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/{_locale}/admin/topbar-sections', name: 'admin_topbar_sections_', requirements: ['_locale' => 'fr|en'])]
final class TopbarSectionController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        SectionRepository $sectionRepository,
        AppLocaleService $appLocaleService,
        Request $request,
    ): Response {
        $menuLocale = $appLocaleService->resolveAdminFilterLocaleFromRequest($request);

        return $this->render('admin/topbar_section/index.html.twig', [
            'sections' => $sectionRepository->findTopbarSections($menuLocale),
            'availableLocales' => $appLocaleService->getContentLocales(),
            'menu_locale' => $menuLocale,
            'menu_locales' => $appLocaleService->getContentLocales(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['POST'])]
    public function new(
        Request $request,
        AppLocaleService $appLocaleService,
        TopbarSectionService $topbarSectionService,
    ): Response {
        $locale = strtolower(trim((string) $request->request->get('locale', $request->getLocale())));
        if (!\in_array($locale, $appLocaleService->getContentLocales(), true)) {
            $locale = $request->getLocale();
        }

        $section = $topbarSectionService->createSection($locale);

        return $this->redirectToRoute('post_new_section', [
            'id' => $section->getId(),
        ]);
    }
}
