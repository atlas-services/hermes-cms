<?php

declare(strict_types=1);

namespace App\Service\Migration;

use App\Service\AdminMediaStorage;
use PDO;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Copie les fichiers médias Hermes 2.2.x vers l’arborescence Vich Hermes 3.
 *
 * - {@code uploads/.../content} (elFinder / admin) : copie récursive avec noms sécurisés (espaces → « _ ») ;
 * - {@code .../entity/Config} : idem ;
 * - images de posts Vich : 2.2.7 {@code entity/section{id}/{menu_code}/} → 3.x
 *   {@code entity/menu{menu_id}/{menu_code}/section{id}/post/} (chemins dérivés de la base migrée).
 */
final class Hermes227MediaMigrator
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly AdminMediaStorage $mediaStorage,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {}

    /**
     * @return array{
     *     configFiles: int,
     *     contentFiles: int,
     *     postsCopied: int,
     *     postsMissing: int,
     *     postsSkipped: int,
     *     filesRenamed: int,
     *     postsFileNameUpdated: int,
     *     contentRowsUpdated: int
     * }
     */
    public function migrate(
        string $dataDb,
        string $fromMediaRoot,
        string $toMediaRoot,
        bool $dryRun,
        bool $overwrite,
        callable $log,
    ): array {
        $dbPath = $this->normalizePath($dataDb);
        if (!is_file($dbPath)) {
            throw new \InvalidArgumentException(sprintf('Base SQLite introuvable : %s', $dbPath));
        }

        $from = $this->resolveMediaBase($fromMediaRoot);
        $to = $this->resolveMediaBase($toMediaRoot);

        /** @var array<string, string> chemins relatifs source → cible (clé et valeur avec /) */
        $pathRenames = [];

        $stats = [
            'configFiles' => 0,
            'contentFiles' => 0,
            'postsCopied' => 0,
            'postsMissing' => 0,
            'postsSkipped' => 0,
            'filesRenamed' => 0,
            'postsFileNameUpdated' => 0,
            'contentRowsUpdated' => 0,
        ];

        $configMirror = $this->mirrorDirectory(
            $from['entity'] . '/Config',
            $to['entity'] . '/Config',
            $dryRun,
            $overwrite,
            $log,
            'Config',
        );
        $stats['configFiles'] = $configMirror['count'];
        $pathRenames = array_merge($pathRenames, $configMirror['renames']);

        $contentMirror = $this->mirrorDirectory(
            $from['content'],
            $to['content'],
            $dryRun,
            $overwrite,
            $log,
            'content (médias admin)',
        );
        $stats['contentFiles'] = $contentMirror['count'];
        $pathRenames = array_merge($pathRenames, $contentMirror['renames']);

        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        foreach ($this->fetchPostsWithMedia($pdo) as $row) {
            $fileName = (string) $row['file_name'];
            $safeFileName = $this->mediaStorage->sanitizeFileName($fileName);
            $sectionId = (int) $row['section_id'];
            $menuId = (int) $row['menu_id'];
            $menuCode = (string) $row['menu_code'];

            $legacyRelative = sprintf('section%d/%s/%s', $sectionId, $menuCode, $fileName);
            $targetRelative = sprintf(
                'menu%d/%s/section%d/post/%s',
                $menuId,
                $menuCode,
                $sectionId,
                $safeFileName,
            );

            if ($fileName !== $safeFileName) {
                $pathRenames['entity/' . $legacyRelative] = 'entity/' . $targetRelative;
            }

            $source = $from['entity'] . '/' . $legacyRelative;
            $dest = $to['entity'] . '/' . $targetRelative;

            if (!is_file($source)) {
                ++$stats['postsMissing'];
                $log(sprintf('Post id=%d : fichier source absent (%s).', $row['id'], $legacyRelative));
                continue;
            }

            if (is_file($dest) && !$overwrite) {
                ++$stats['postsSkipped'];
                continue;
            }

            if (!$dryRun) {
                $this->filesystem->mkdir(\dirname($dest));
                $this->filesystem->copy($source, $dest, true);
                if ($fileName !== $safeFileName) {
                    $stmt = $pdo->prepare('UPDATE post SET file_name = ? WHERE id = ?');
                    $stmt->execute([$safeFileName, $row['id']]);
                    ++$stats['postsFileNameUpdated'];
                }
            } elseif ($fileName !== $safeFileName) {
                ++$stats['postsFileNameUpdated'];
            }
            ++$stats['postsCopied'];
        }

        foreach ($pathRenames as $old => $new) {
            if ($old !== $new) {
                ++$stats['filesRenamed'];
            }
        }

        if ($pathRenames !== []) {
            $stats['contentRowsUpdated'] = $this->rewritePostContentMediaPaths($pdo, $pathRenames, $dryRun);
            if ($stats['contentRowsUpdated'] > 0) {
                $log(sprintf(
                    '%d publication(s) : chemins médias mis à jour dans le HTML (espaces → « _ »).',
                    $stats['contentRowsUpdated'],
                ));
            }
        }

        return $stats;
    }

    /**
     * @return array{entity: string, content: string}
     */
    private function resolveMediaBase(string $path): array
    {
        $path = $this->normalizePath($path);
        if (is_dir($path . '/entity')) {
            $base = $path;
        } elseif (basename($path) === 'entity') {
            $base = \dirname($path);
        } elseif (is_dir($path) || (!is_file($path) && !file_exists($path))) {
            $base = $path;
            if (!is_dir($base)) {
                $this->filesystem->mkdir($base);
            }
        } else {
            throw new \InvalidArgumentException(sprintf(
                'Répertoire média invalide (attendu …/uploads ou …/uploads/entity) : %s',
                $path,
            ));
        }

        return [
            'entity' => $base . '/entity',
            'content' => $base . '/content',
        ];
    }

    /**
     * @return array{count: int, renames: array<string, string>}
     */
    private function mirrorDirectory(
        string $fromDir,
        string $toDir,
        bool $dryRun,
        bool $overwrite,
        callable $log,
        string $label,
    ): array {
        if (!is_dir($fromDir)) {
            $log(sprintf('Aucun répertoire « %s » en source (%s) — ignoré.', $label, $fromDir));

            return ['count' => 0, 'renames' => []];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fromDir, \FilesystemIterator::SKIP_DOTS),
        );

        $files = [];
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                $files[] = $fileInfo->getPathname();
            }
        }

        if ($files === []) {
            return ['count' => 0, 'renames' => []];
        }

        if (!$dryRun) {
            if (is_dir($toDir) && $overwrite) {
                $this->filesystem->remove($toDir);
            }
            $this->filesystem->mkdir($toDir);
        }

        $renames = [];
        $fromPrefix = rtrim(str_replace('\\', '/', $fromDir), '/');

        foreach ($files as $sourcePath) {
            $sourceNorm = str_replace('\\', '/', $sourcePath);
            $rel = ltrim(substr($sourceNorm, \strlen($fromPrefix)), '/');
            try {
                $safeRel = $this->mediaStorage->normalizeRelativePath($rel);
            } catch (\InvalidArgumentException) {
                $log(sprintf('Fichier ignoré (chemin invalide) : %s', $rel));

                continue;
            }

            if ($rel !== $safeRel) {
                $renames[$rel] = $safeRel;
            }

            if ($dryRun) {
                continue;
            }

            $destPath = $toDir . '/' . str_replace('/', \DIRECTORY_SEPARATOR, $safeRel);
            $this->filesystem->mkdir(\dirname($destPath));
            $this->filesystem->copy($sourcePath, $destPath, true);
        }

        $log(sprintf('%d fichier(s) « %s » %s.', \count($files), $label, $dryRun ? 'à copier (dry-run)' : 'copié(s)'));

        return ['count' => \count($files), 'renames' => $renames];
    }

    /**
     * @param array<string, string> $pathRenames clés / valeurs : chemins relatifs (ex. content/sub/a b.jpg)
     */
    private function rewritePostContentMediaPaths(PDO $pdo, array $pathRenames, bool $dryRun): int
    {
        if (!$this->tableExists($pdo, 'post')) {
            return 0;
        }

        $replacements = $this->buildContentReplacements($pathRenames);
        if ($replacements === []) {
            return 0;
        }

        $updated = 0;
        $stmt = $pdo->query('SELECT id, content FROM post WHERE content IS NOT NULL AND TRIM(content) != \'\'');
        $rows = $stmt->fetchAll();
        $update = $pdo->prepare('UPDATE post SET content = ? WHERE id = ?');

        foreach ($rows as $row) {
            $content = (string) $row['content'];
            $newContent = $this->applyReplacements($content, $replacements);
            if ($newContent === $content) {
                continue;
            }
            if (!$dryRun) {
                $update->execute([$newContent, $row['id']]);
            }
            ++$updated;
        }

        return $updated;
    }

    /**
     * @param array<string, string> $pathRenames
     *
     * @return list<array{search: string, replace: string}>
     */
    private function buildContentReplacements(array $pathRenames): array
    {
        $pairs = [];
        $seen = [];

        foreach ($pathRenames as $old => $new) {
            if ($old === $new) {
                continue;
            }

            foreach ($this->replacementVariants($old, $new) as $variant) {
                $key = $variant['search'] . "\0" . $variant['replace'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $pairs[] = $variant;
            }
        }

        usort($pairs, static fn (array $a, array $b): int => \strlen($b['search']) <=> \strlen($a['search']));

        return $pairs;
    }

    /**
     * @return list<array{search: string, replace: string}>
     */
    private function replacementVariants(string $oldRel, string $newRel): array
    {
        $variants = [
            ['search' => $oldRel, 'replace' => $newRel],
            ['search' => str_replace(' ', '%20', $oldRel), 'replace' => $newRel],
        ];

        foreach (['content/', 'entity/'] as $prefix) {
            if (!str_starts_with($oldRel, $prefix) || !str_starts_with($newRel, $prefix)) {
                continue;
            }
            $shortOld = substr($oldRel, \strlen($prefix));
            $shortNew = substr($newRel, \strlen($prefix));
            $variants[] = ['search' => $shortOld, 'replace' => $shortNew];
            $variants[] = ['search' => str_replace(' ', '%20', $shortOld), 'replace' => $shortNew];
        }

        $oldBase = basename($oldRel);
        $newBase = basename($newRel);
        if ($oldBase !== $newBase) {
            $variants[] = ['search' => $oldBase, 'replace' => $newBase];
            $variants[] = ['search' => str_replace(' ', '%20', $oldBase), 'replace' => $newBase];
            $variants[] = ['search' => rawurlencode($oldBase), 'replace' => $newBase];
        }

        return $variants;
    }

    /**
     * @param list<array{search: string, replace: string}> $replacements
     */
    private function applyReplacements(string $content, array $replacements): string
    {
        foreach ($replacements as $pair) {
            if ($pair['search'] === '' || $pair['search'] === $pair['replace']) {
                continue;
            }
            $content = str_replace($pair['search'], $pair['replace'], $content);
        }

        return $content;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPostsWithMedia(PDO $pdo): array
    {
        if (!$this->tableExists($pdo, 'post') || !$this->tableExists($pdo, 'section') || !$this->tableExists($pdo, 'menu')) {
            throw new \InvalidArgumentException('La base doit contenir les tables post, section et menu (base Hermes 3 migrée).');
        }

        $sql = <<<'SQL'
SELECT p.id, p.file_name, s.id AS section_id, m.id AS menu_id, m.code AS menu_code
FROM post p
INNER JOIN section s ON s.id = p.section_id
INNER JOIN menu m ON m.id = s.menu_id
WHERE p.file_name IS NOT NULL AND TRIM(p.file_name) != ''
ORDER BY p.id ASC
SQL;

        return $pdo->query($sql)->fetchAll();
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            throw new \InvalidArgumentException('Chemin vide.');
        }
        if (!str_starts_with($path, '/')) {
            $path = $this->projectDir . '/' . ltrim($path, '/');
        }
        $dir = \dirname($path);
        $base = basename($path);
        $realDir = realpath($dir);

        return $realDir === false ? $path : $realDir . \DIRECTORY_SEPARATOR . $base;
    }
}
