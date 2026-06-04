<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\LegalPagesApiTemplateMatcher;
use App\Service\LegalPagesInitializer;
use PHPUnit\Framework\TestCase;

final class LegalPagesApiTemplateMatcherTest extends TestCase
{
    public function testResolvesFirstMatchingTemplatePerPage(): void
    {
        $catalog = [
            ['iri' => 'https://api.example.com/api/templates/99', 'label' => 'Autre', 'description' => ''],
            ['iri' => 'https://api.example.com/api/templates/mentions-legales', 'label' => 'Mentions légales Atlas', 'description' => ''],
            ['iri' => 'https://api.example.com/api/templates/confidentialite', 'label' => 'Confidentialité', 'description' => 'RGPD'],
            ['iri' => 'https://api.example.com/api/templates/cgu-cgv', 'label' => 'CGU CGV', 'description' => ''],
        ];

        $matcher = new LegalPagesApiTemplateMatcher();
        $resolved = $matcher->resolveForPages(LegalPagesInitializer::PAGES, $catalog);

        self::assertSame('https://api.example.com/api/templates/mentions-legales', $resolved['mentions-legales']['iri']);
        self::assertSame('https://api.example.com/api/templates/confidentialite', $resolved['confidentialite']['iri']);
        self::assertSame('https://api.example.com/api/templates/cgu-cgv', $resolved['cgu-cgv']['iri']);
    }

    public function testDoesNotReuseSameCatalogEntryTwice(): void
    {
        $catalog = [
            ['iri' => 'https://api.example.com/api/templates/legal-bundle', 'label' => 'mentions-legales et confidentialite', 'description' => 'cgu-cgv'],
        ];

        $matcher = new LegalPagesApiTemplateMatcher();
        $resolved = $matcher->resolveForPages(LegalPagesInitializer::PAGES, $catalog);

        self::assertNotNull($resolved['mentions-legales']);
        self::assertNull($resolved['confidentialite']);
        self::assertNull($resolved['cgu-cgv']);
    }
}
