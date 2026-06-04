<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\LegalPagesApiTemplateMatcher;
use App\Service\LegalPagesInitializer;
use PHPUnit\Framework\TestCase;

final class LegalPagesApiTemplateMatcherTest extends TestCase
{
    public function testResolvesByApiTypeField(): void
    {
        $catalog = [
            ['iri' => 'https://api.example.com/api/templates/99', 'label' => 'Autre', 'description' => '', 'type' => 'folio'],
            ['iri' => 'https://api.example.com/api/templates/1', 'label' => 'ML', 'description' => '', 'type' => 'mentions-legales'],
            ['iri' => 'https://api.example.com/api/templates/2', 'label' => 'Conf', 'description' => '', 'type' => 'confidentialite'],
            ['iri' => 'https://api.example.com/api/templates/3', 'label' => 'CGU', 'description' => '', 'type' => 'cgu-cgv'],
        ];

        $matcher = new LegalPagesApiTemplateMatcher();
        $resolved = $matcher->resolveForPages(LegalPagesInitializer::PAGES, $catalog);

        self::assertSame('https://api.example.com/api/templates/1', $resolved['mentions-legales']['iri']);
        self::assertSame('mentions-legales', $resolved['mentions-legales']['type']);
        self::assertSame('https://api.example.com/api/templates/2', $resolved['confidentialite']['iri']);
        self::assertSame('https://api.example.com/api/templates/3', $resolved['cgu-cgv']['iri']);
    }

    public function testIgnoresLabelMatchWithoutMatchingType(): void
    {
        $catalog = [
            [
                'iri' => 'https://api.example.com/api/templates/bundle',
                'label' => 'mentions-legales et confidentialite',
                'description' => 'cgu-cgv',
                'type' => 'libre',
            ],
        ];

        $matcher = new LegalPagesApiTemplateMatcher();
        $resolved = $matcher->resolveForPages(LegalPagesInitializer::PAGES, $catalog);

        self::assertNull($resolved['mentions-legales']);
        self::assertNull($resolved['confidentialite']);
        self::assertNull($resolved['cgu-cgv']);
    }

    public function testDoesNotReuseSameCatalogEntryTwice(): void
    {
        $catalog = [
            [
                'iri' => 'https://api.example.com/api/templates/dup',
                'label' => 'Dup',
                'description' => '',
                'type' => 'mentions-legales',
            ],
        ];

        $matcher = new LegalPagesApiTemplateMatcher();
        $resolved = $matcher->resolveForPages(LegalPagesInitializer::PAGES, $catalog);

        self::assertNotNull($resolved['mentions-legales']);
        self::assertNull($resolved['confidentialite']);
        self::assertNull($resolved['cgu-cgv']);
    }
}
