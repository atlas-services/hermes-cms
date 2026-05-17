<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\HermesApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/{_locale}/admin/hermes-api')]
final class HermesLibreApiController extends AbstractController
{
    #[Route('/libre/catalog', name: 'admin_hermes_api_libre_catalog', methods: ['GET'])]
    public function libreCatalog(Request $request, HermesApiClient $hermesApiClient): JsonResponse
    {
        if (!$hermesApiClient->isLibreCatalogConfigured()) {
            return $this->json([
                'enabled' => false,
                'items' => [],
                'hint' => 'Définissez API_HERMES_BASE_URL, API_HERMES_TEMPLATES, API_HERMES_EMAIL et API_HERMES_PASSWORD (.env). Le JWT est obtenu via POST /api/login (chiffrement libsodium, comme Hermes 2.2.7) et stocké en session.',
            ]);
        }

        if ($request->hasSession()) {
            $request->getSession()->start();
        }

        $items = $hermesApiClient->fetchLibreTemplateSummaries();
        $payload = [
            'enabled' => true,
            'items' => $items,
        ];
        if ($items === [] && $this->getParameter('kernel.debug')) {
            $payload['diagnostics'] = $hermesApiClient->getCatalogDiagnostics();
        }

        return $this->json($payload);
    }

    #[Route('/libre/html', name: 'admin_hermes_api_libre_html', methods: ['GET'])]
    public function libreHtml(Request $request, HermesApiClient $hermesApiClient): JsonResponse
    {
        $iri = (string) $request->query->get('iri', '');
        if ($iri === '') {
            return $this->json(['error' => 'missing_iri'], Response::HTTP_BAD_REQUEST);
        }

        $html = $hermesApiClient->fetchLibreTemplateHtml($iri);
        if ($html === null) {
            return $this->json(['error' => 'not_found_or_empty'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['html' => $html]);
    }
}
