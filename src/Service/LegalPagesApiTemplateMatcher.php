<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Associe chaque page légale au premier modèle API Hermes dont le champ {@code type} correspond au slug de page.
 */
final class LegalPagesApiTemplateMatcher
{
    /** @var list<string> */
    public const LEGAL_PAGE_TYPES = ['mentions-legales', 'confidentialite', 'cgu-cgv'];

    /**
     * @param list<array{slug: string}> $pages ordre de résolution (ex. LegalPagesInitializer::PAGES)
     * @param list<array{iri: string, label: string, description: string, type: string|null}> $catalogSummaries
     *
     * @return array<string, array{iri: string, label: string, type: string}|null> indexé par slug de page
     */
    public function resolveForPages(array $pages, array $catalogSummaries): array
    {
        $resolved = [];
        $usedIris = [];

        foreach ($pages as $page) {
            $slug = $page['slug'];
            $match = $this->findFirstByType($slug, $catalogSummaries, $usedIris);
            $resolved[$slug] = $match;
            if ($match !== null) {
                $usedIris[] = $match['iri'];
            }
        }

        return $resolved;
    }

    /**
     * @param list<array{iri: string, label: string, description: string, type: string|null}> $catalogSummaries
     * @param list<string> $usedIris
     *
     * @return array{iri: string, label: string, type: string}|null
     */
    private function findFirstByType(string $pageSlug, array $catalogSummaries, array $usedIris): ?array
    {
        foreach ($catalogSummaries as $row) {
            if (\in_array($row['iri'], $usedIris, true)) {
                continue;
            }

            $type = $row['type'] ?? null;
            if ($type === null || $type !== $pageSlug) {
                continue;
            }

            return [
                'iri' => $row['iri'],
                'label' => $row['label'],
                'type' => $type,
            ];
        }

        return null;
    }
}
