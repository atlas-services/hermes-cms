<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Associe chaque page légale au premier modèle du catalogue API Hermes dont le libellé, la description ou l’IRI correspond.
 */
final class LegalPagesApiTemplateMatcher
{
    /** @var array<string, list<string>> */
    private const PAGE_PATTERNS = [
        'mentions-legales' => [
            'mentions-legales',
            'mentions_legales',
            'mentions legales',
            'mentions légales',
            'mention-legales',
            'mention_legales',
        ],
        'confidentialite' => [
            'confidentialite',
            'confidentialité',
            'politique-de-confidentialite',
            'politique_de_confidentialite',
            'politique confidentialite',
            'privacy',
        ],
        'cgu-cgv' => [
            'cgu-cgv',
            'cgu_cgv',
            'cgu/cgv',
            'cgu cgv',
            'cgv-cgu',
            'conditions-generales',
            'conditions generales',
        ],
    ];

    /**
     * @param list<array{slug: string}> $pages ordre de résolution (ex. LegalPagesInitializer::PAGES)
     * @param list<array{iri: string, label: string, description: string}> $catalogSummaries
     *
     * @return array<string, array{iri: string, label: string}|null> indexé par slug de page
     */
    public function resolveForPages(array $pages, array $catalogSummaries): array
    {
        $resolved = [];
        $usedIris = [];

        foreach ($pages as $page) {
            $slug = $page['slug'];
            $match = $this->findFirstMatch($slug, $catalogSummaries, $usedIris);
            $resolved[$slug] = $match;
            if ($match !== null) {
                $usedIris[] = $match['iri'];
            }
        }

        return $resolved;
    }

    /**
     * @param list<array{iri: string, label: string, description: string}> $catalogSummaries
     * @param list<string> $usedIris
     *
     * @return array{iri: string, label: string}|null
     */
    private function findFirstMatch(string $pageSlug, array $catalogSummaries, array $usedIris): ?array
    {
        $patterns = self::PAGE_PATTERNS[$pageSlug] ?? [$pageSlug];

        foreach ($catalogSummaries as $row) {
            if (\in_array($row['iri'], $usedIris, true)) {
                continue;
            }

            if ($this->matchesPage($pageSlug, $patterns, $row)) {
                return [
                    'iri' => $row['iri'],
                    'label' => $row['label'],
                ];
            }
        }

        return null;
    }

    /**
     * @param list<string> $patterns
     * @param array{iri: string, label: string, description: string} $row
     */
    private function matchesPage(string $pageSlug, array $patterns, array $row): bool
    {
        $haystack = strtolower($row['label'] . ' ' . $row['description'] . ' ' . $row['iri']);

        foreach ($patterns as $pattern) {
            if (str_contains($haystack, strtolower($pattern))) {
                return true;
            }
        }

        return str_contains(strtolower($row['iri']), $pageSlug)
            || str_contains(strtolower($row['label']), $pageSlug);
    }
}
