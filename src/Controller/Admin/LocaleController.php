<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Form\Admin\LocaleCopyType;
use App\Service\AppLocaleService;
use App\Service\LocaleCopyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/{_locale}/admin/locale', name: 'admin_locale_', requirements: ['_locale' => 'fr|en'])]
final class LocaleController extends AbstractController
{
    #[Route('/nouvelle-langue', name: 'new', methods: ['GET', 'POST'])]
    public function newLocale(
        Request $request,
        LocaleCopyService $localeCopyService,
        AppLocaleService $appLocaleService,
    ): Response {
        $sourceLocale = $localeCopyService->resolveDefaultSourceLocale();
        $localeChoices = $appLocaleService->getAvailableTargetLocaleChoices($sourceLocale);

        if ($localeChoices === []) {
            $this->addFlash('warning', 'Toutes les langues disponibles possèdent déjà des menus ou des posts.');
        }

        $form = $this->createForm(LocaleCopyType::class, null, [
            'locale_choices' => $localeChoices,
            'source_locale' => $sourceLocale,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $targetLocale = (string) $form->get('locale')->getData();
            $result = $localeCopyService->copyLocale($targetLocale, $sourceLocale);
            if (isset($result['success'])) {
                $this->addFlash('success', $result['success']);
            } else {
                $this->addFlash('warning', $result['warning'] ?? 'Copie impossible.');
            }

            return $this->redirectToRoute('menu_index', [
                '_locale' => $request->getLocale(),
                'menu_locale' => $targetLocale,
            ]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', 'Le formulaire est invalide (vérifiez la langue choisie et réessayez).');
        }

        return $this->render('admin/locale/new.html.twig', [
            'form' => $form,
            'source_locale' => $sourceLocale,
            'source_locale_label' => $appLocaleService->formatLabel($sourceLocale),
        ]);
    }
}
