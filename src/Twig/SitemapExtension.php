<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\SitemapBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

final class SitemapExtension
{
    public function __construct(
        private readonly SitemapBuilder $sitemapBuilder,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Entrées HTML du plan du site (groupées par menu racine), pour la modale footer.
     *
     * @return array<string, list<array<string, string>>>
     */
    #[AsTwigFunction('hermes_sitemap_html')]
    public function sitemapHtml(): array
    {
        $request = $this->requestStack->getMainRequest();
        $locale = $request?->getLocale() ?? 'fr';

        return $this->sitemapBuilder->build($locale)['html'];
    }
}
