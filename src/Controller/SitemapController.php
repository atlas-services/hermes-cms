<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SitemapBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SitemapController extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'app.default_locale')]
        private readonly string $defaultLocale,
    ) {
    }

    #[Route('/sitemap.xml', name: 'front_sitemap_redirect', methods: ['GET'])]
    public function redirectToLocaleSitemap(): Response
    {
        return $this->redirectToRoute(
            'front_sitemap',
            ['_locale' => $this->defaultLocale],
            Response::HTTP_MOVED_PERMANENTLY,
        );
    }

    #[Route('/robots.txt', name: 'front_robots', methods: ['GET'])]
    public function robots(Request $request): Response
    {
        $host = $request->getSchemeAndHttpHost();

        $body = <<<TXT
User-agent: *
Disallow: /admin/
Disallow: /login/
Allow: /

Sitemap: {$host}/sitemap.xml
TXT;

        return new Response($body, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    #[Route('/{_locale}/sitemap.xml', name: 'front_sitemap', requirements: ['_locale' => 'fr|en'], defaults: ['_locale' => 'fr'], methods: ['GET'])]
    public function xml(Request $request, SitemapBuilder $sitemapBuilder): Response
    {
        $locale = $request->getLocale();
        $urls = $sitemapBuilder->build($locale, $request->getSchemeAndHttpHost())['xml'];

        $response = new Response(
            $this->renderView('front/sitemap/sitemap.xml.twig', ['urls' => $urls]),
            Response::HTTP_OK,
        );
        $response->headers->set('Content-Type', 'text/xml; charset=UTF-8');

        return $response;
    }
}
