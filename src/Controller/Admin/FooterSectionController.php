<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\SectionRepository;
use App\Service\FooterSectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/{_locale}/admin/footer-sections', name: 'admin_footer_sections_', requirements: ['_locale' => 'fr|en'])]
final class FooterSectionController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(SectionRepository $sectionRepository, Request $request): Response
    {
        return $this->render('admin/footer_section/index.html.twig', [
            'sections' => $sectionRepository->findFooterSections($request->getLocale()),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['POST'])]
    public function new(Request $request, FooterSectionService $footerSectionService): Response
    {
        $section = $footerSectionService->createSection($request->getLocale());

        return $this->redirectToRoute('post_new_section', [
            'id' => $section->getId(),
        ]);
    }
}
