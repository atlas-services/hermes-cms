<?php

declare(strict_types=1);

namespace App\Service\Migration;

use PDO;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Copie les fichiers médias Hermes 2.2.x vers l’arborescence Vich Hermes 3.
 *
 * - {@code uploads/.../content} (elFinder / admin) : copie récursive identique ;
 * - {@code .../entity/Config} : copie récursive identique ;
 * - images de posts Vich : 2.2.7 {@code entity/section{id}/{menu_code}/} → 3.x
 *   {@code entity/menu{menu_id}/{menu_code}/section{id}/post/} (chemins dérivés de la base migrée).
 */
final class Hermes227MediaMigrator
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {}

    /**
     * @return array{configFiles: int, contentFiles: int, postsCopied: int, postsMissing: int, postsSkipped: int}
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

        $stats = [
            'configFiles' => 0,
            'contentFiles' => 0,
            'postsCopied' => 0,
            'postsMissing' => 0,
            'postsSkipped' => 0,
        ];

        $stats['configFiles'] = $this->mirrorDirectory(
            $from['entity'] . '/Config',
            $to['entity'] . '/Config',
            $dryRun,
            $overwrite,
            $log,
            'Config',
        );

        $stats['contentFiles'] = $this->mirrorDirectory(
            $from['content'],
            $to['content'],
            $dryRun,
            $overwrite,
            $log,
            'content (médias admin)',
        );

        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        foreach ($this->fetchPostsWithMedia($pdo) as $row) {
            $fileName = (string) $row['file_name'];
            $sectionId = (int) $row['section_id'];
            $menuId = (int) $row['menu_id'];
            $menuCode = (string) $row['menu_code'];

            $legacyRelative = sprintf('section%d/%s/%s', $sectionId, $menuCode, $fileName);
            $targetRelative = sprintf(
                'menu%d/%s/section%d/post/%s',
                $menuId,
                $menuCode,
                $sectionId,
                $fileName,
            );

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
            }
            ++$stats['postsCopied'];
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
            // Cible vide ou racine uploads à créer (entity/, content/).
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

    private function mirrorDirectory(
        string $fromDir,
        string $toDir,
        bool $dryRun,
        bool $overwrite,
        callable $log,
        string $label,
    ): int {
        if (!is_dir($fromDir)) {
            $log(sprintf('Aucun répertoire « %s » en source (%s) — ignoré.', $label, $fromDir));

            return 0;
        }

        $count = $this->countFilesRecursive($fromDir);
        if ($count === 0) {
            return 0;
        }

        if (!$dryRun) {
            if (is_dir($toDir) && $overwrite) {
                $this->filesystem->remove($toDir);
            }
            $this->filesystem->mirror($fromDir, $toDir);
        }

        $log(sprintf('%d fichier(s) « %s » %s.', $count, $label, $dryRun ? 'à copier (dry-run)' : 'copié(s)'));

        return $count;
    }

    private function countFilesRecursive(string $dir): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                ++$count;
            }
        }

        return $count;
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
