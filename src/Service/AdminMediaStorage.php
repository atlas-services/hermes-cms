<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Arborescence médias sous public/{APP_PATH_CONTENT_IMAGES_POSTS} (chemins relatifs normalisés).
 */
final class AdminMediaStorage
{
    public const int MAX_DEPTH = 24;

    public const int MAX_SEGMENT_LEN = 120;

    public function __construct(
        #[Autowire('%hermes_admin_media_upload_dir%')]
        private readonly string $rootFs,
        #[Autowire('%hermes_path_content_image_post%')]
        private readonly string $webBasePath,
    ) {
    }

    public function getRootFs(): string
    {
        return $this->rootFs;
    }

    /** Préfixe URL sous la racine web (ex. uploads/content), sans slash initial/final. */
    public function getWebBasePath(): string
    {
        return trim(str_replace('\\', '/', $this->webBasePath), '/');
    }

    /**
     * Chemin relatif normalisé (sans ..), ou chaîne vide pour la racine.
     *
     * @throws \InvalidArgumentException
     */
    public function normalizeRelativePath(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }
        $path = str_replace('\\', '/', $path);
        $path = trim($path, '/');
        if ($path === '') {
            return '';
        }

        $parts = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Invalid path');
            }
            $clean = $this->sanitizeSegment($segment);
            if ($clean === '') {
                throw new \InvalidArgumentException('Invalid path segment');
            }
            $parts[] = $clean;
            if (count($parts) > self::MAX_DEPTH) {
                throw new \InvalidArgumentException('Path too deep');
            }
        }

        return implode('/', $parts);
    }

    public function filesystemPath(string $relativePath): string
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === '') {
            return $this->rootFs;
        }

        return $this->rootFs . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    /** URL relative depuis la racine du site : uploads/content/... */
    public function webRelativeUrl(string $relativePath): string
    {
        $rel = $this->normalizeRelativePath($relativePath);
        $base = $this->getWebBasePath();

        return $base . ($rel !== '' ? '/' . $rel : '');
    }

    /**
     * @return array{
     *     directories: list<array{name: string, path: string}>,
     *     files: list<array{name: string, path: string, size: int, has_thumb: bool}>
     * }
     */
    public function listDirectory(string $relativePath): array
    {
        $full = $this->filesystemPath($relativePath);
        if (!is_dir($full) || !is_readable($full)) {
            throw new \RuntimeException('Cannot read directory');
        }

        $directories = [];
        $files = [];
        $baseRel = $this->normalizeRelativePath($relativePath);

        foreach (scandir($full) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (str_starts_with($name, '.')) {
                continue;
            }
            $childRel = $baseRel !== '' ? $baseRel . '/' . $name : $name;
            $childFs = $full . DIRECTORY_SEPARATOR . $name;
            if (is_dir($childFs)) {
                $directories[] = ['name' => $name, 'path' => $childRel];
            } elseif (is_file($childFs)) {
                $files[] = [
                    'name' => $name,
                    'path' => $childRel,
                    'size' => (int) (@filesize($childFs) ?: 0),
                    'has_thumb' => self::hasListingThumbnail($name),
                ];
            }
        }

        usort($directories, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        usort($files, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return ['directories' => $directories, 'files' => $files];
    }

    /**
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function createSubdirectory(string $parentRelative, string $name): string
    {
        $parent = $this->normalizeRelativePath($parentRelative);
        $cleanName = $this->sanitizeSegment($name);
        if ($cleanName === '') {
            throw new \InvalidArgumentException('Invalid folder name');
        }
        $depth = $parent !== '' ? substr_count($parent, '/') + 1 : 0;
        if ($depth + 1 > self::MAX_DEPTH) {
            throw new \InvalidArgumentException('Path too deep');
        }
        $newRel = $parent !== '' ? $parent . '/' . $cleanName : $cleanName;
        $full = $this->filesystemPath($newRel);
        if (file_exists($full)) {
            throw new \RuntimeException('Already exists');
        }
        if (!@mkdir($full, 0775, true) && !is_dir($full)) {
            throw new \RuntimeException('mkdir failed');
        }

        return $newRel;
    }

    /**
     * Renomme un dossier (change uniquement le dernier segment du chemin).
     *
     * @return string Nouveau chemin relatif
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function renameDirectory(string $relativeDir, string $newBaseName): string
    {
        $relativeDir = $this->normalizeRelativePath($relativeDir);
        if ($relativeDir === '') {
            throw new \InvalidArgumentException('Cannot rename root');
        }
        $newBase = $this->sanitizeSegment($newBaseName);
        if ($newBase === '') {
            throw new \InvalidArgumentException('Invalid folder name');
        }
        $norm = str_replace('\\', '/', $relativeDir);
        $parent = dirname($norm);
        if ($parent === '.') {
            $parent = '';
        }
        $newRel = $parent !== '' ? $parent . '/' . $newBase : $newBase;
        if ($newRel === $relativeDir) {
            return $relativeDir;
        }

        $oldFs = $this->filesystemPath($relativeDir);
        $newFs = $this->filesystemPath($newRel);
        if (!is_dir($oldFs)) {
            throw new \RuntimeException('Not a directory');
        }
        if (file_exists($newFs)) {
            throw new \RuntimeException('Target exists');
        }
        if (!@rename($oldFs, $newFs)) {
            throw new \RuntimeException('Rename failed');
        }

        return $this->normalizeRelativePath($newRel);
    }

    /**
     * Supprime un dossier et tout son contenu (ne permet pas la racine média).
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function deleteDirectory(string $relativeDir): void
    {
        $relativeDir = $this->normalizeRelativePath($relativeDir);
        if ($relativeDir === '') {
            throw new \InvalidArgumentException('Cannot delete root');
        }
        $full = $this->filesystemPath($relativeDir);
        $rootReal = realpath($this->rootFs);
        $targetReal = realpath($full);
        if ($rootReal === false) {
            throw new \RuntimeException('Media root not found');
        }
        if ($targetReal === false || !is_dir($targetReal)) {
            throw new \RuntimeException('Not a directory');
        }
        $rootNorm = rtrim(str_replace('\\', '/', $rootReal), '/');
        $targetNorm = rtrim(str_replace('\\', '/', $targetReal), '/');
        if (!str_starts_with($targetNorm, $rootNorm)) {
            throw new \InvalidArgumentException('Outside media root');
        }
        if ($targetNorm === $rootNorm) {
            throw new \InvalidArgumentException('Cannot delete root');
        }

        (new Filesystem())->remove($full);
    }

    /**
     * Supprime un fichier (pas un répertoire) sous la racine média.
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function deleteFile(string $relativeFilePath): void
    {
        $relativeFilePath = $this->normalizeRelativePath($relativeFilePath);
        if ($relativeFilePath === '') {
            throw new \InvalidArgumentException('Invalid file path');
        }
        $full = $this->filesystemPath($relativeFilePath);
        $rootReal = realpath($this->rootFs);
        if ($rootReal === false) {
            throw new \RuntimeException('Media root not found');
        }
        if (!file_exists($full)) {
            throw new \RuntimeException('File not found');
        }
        $targetReal = realpath($full);
        if ($targetReal === false) {
            throw new \RuntimeException('File not found');
        }
        if (!is_file($targetReal)) {
            throw new \RuntimeException('Not a file');
        }
        $rootNorm = rtrim(str_replace('\\', '/', $rootReal), '/');
        $targetNorm = str_replace('\\', '/', $targetReal);
        if (!str_starts_with($targetNorm, $rootNorm . '/') && $targetNorm !== $rootNorm) {
            throw new \InvalidArgumentException('Outside media root');
        }

        (new Filesystem())->remove($full);
    }

    /** Dossier parent relatif, ou chaîne vide pour la racine. */
    public function parentRelativePath(string $relativePath): string
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        if ($relativePath === '') {
            return '';
        }
        $norm = str_replace('\\', '/', $relativePath);
        $parent = dirname($norm);
        if ($parent === '.') {
            return '';
        }

        return $this->normalizeRelativePath($parent);
    }

    /**
     * Après renommage old → new, chemin de listing à utiliser (breadcrumb cohérent).
     */
    public function listingPathAfterRename(string $currentListingPath, string $oldDirPath, string $newDirPath): string
    {
        $currentListingPath = $this->normalizeRelativePath($currentListingPath);
        $oldDirPath = $this->normalizeRelativePath($oldDirPath);
        $newDirPath = $this->normalizeRelativePath($newDirPath);
        if ($currentListingPath === $oldDirPath) {
            return $newDirPath;
        }
        $prefix = $oldDirPath . '/';
        if ($oldDirPath !== '' && str_starts_with($currentListingPath, $prefix)) {
            return $newDirPath . '/' . substr($currentListingPath, strlen($prefix));
        }

        return $currentListingPath;
    }

    /**
     * Après suppression d’un dossier, chemin de listing sûr (hors arbre supprimé).
     */
    public function listingPathAfterDelete(string $currentListingPath, string $deletedDirPath): string
    {
        $currentListingPath = $this->normalizeRelativePath($currentListingPath);
        $deletedDirPath = $this->normalizeRelativePath($deletedDirPath);
        if ($deletedDirPath === '') {
            return $currentListingPath;
        }
        if ($currentListingPath === $deletedDirPath || str_starts_with($currentListingPath, $deletedDirPath . '/')) {
            return $this->parentRelativePath($deletedDirPath);
        }

        return $currentListingPath;
    }

    private static function hasListingThumbnail(string $filename): bool
    {
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    /**
     * Chemin absolu du fichier à enregistrer (sans dédoublonnage).
     *
     * @throws \InvalidArgumentException
     */
    public function resolveUploadTargetAbsolute(string $uploadBaseRelative, string $webkitRelativePath, string $clientOriginalName): string
    {
        $base = $this->normalizeRelativePath($uploadBaseRelative);
        $webkit = trim(str_replace('\\', '/', $webkitRelativePath), '/');

        if ($webkit === '') {
            $file = $this->sanitizeSegment(basename((string) $clientOriginalName)) ?: 'file';
            $rel = $base !== '' ? $base . '/' . $file : $file;
            $this->assertDepth($rel);

            return $this->filesystemPath($rel);
        }

        $parts = explode('/', $webkit);
        $safe = [];
        foreach ($parts as $p) {
            if ($p === '' || $p === '.' || $p === '..') {
                throw new \InvalidArgumentException('Invalid webkit path');
            }
            $seg = $this->sanitizeSegment($p);
            if ($seg === '') {
                throw new \InvalidArgumentException('Invalid webkit path');
            }
            $safe[] = $seg;
        }
        $relFromWebkit = implode('/', $safe);
        $combined = $base !== '' ? $base . '/' . $relFromWebkit : $relFromWebkit;
        $this->assertDepth($combined);

        return $this->filesystemPath($combined);
    }

    /** Si le fichier existe déjà, ajoute _1, _2, … avant l’extension. */
    public function dedupeFileAbsolutePath(string $absoluteFilePath): string
    {
        if (!file_exists($absoluteFilePath)) {
            return $absoluteFilePath;
        }
        $dir = dirname($absoluteFilePath);
        $baseName = basename($absoluteFilePath);
        $ext = pathinfo($baseName, PATHINFO_EXTENSION);
        $stem = pathinfo($baseName, PATHINFO_FILENAME);
        $i = 1;
        do {
            $suffix = $ext !== '' ? $stem . '_' . $i . '.' . $ext : $stem . '_' . $i;
            $candidate = $dir . DIRECTORY_SEPARATOR . $suffix;
            ++$i;
        } while (file_exists($candidate));

        return $candidate;
    }

    /** Chemin relatif (sous la racine média) à partir d’un chemin absolu enregistré. */
    public function relativePathFromAbsolute(string $absoluteFile): string
    {
        $root = realpath($this->rootFs);
        $file = realpath($absoluteFile);
        if ($root === false || $file === false) {
            throw new \InvalidArgumentException('Path resolution failed');
        }
        $rootNorm = rtrim(str_replace('\\', '/', $root), '/');
        $fileNorm = str_replace('\\', '/', $file);
        if (!str_starts_with($fileNorm, $rootNorm)) {
            throw new \InvalidArgumentException('Outside media root');
        }
        $sub = substr($fileNorm, strlen($rootNorm));

        return ltrim($sub, '/');
    }

    private function assertDepth(string $relativePath): void
    {
        $relativePath = trim($relativePath, '/');
        if ($relativePath === '') {
            return;
        }
        $depth = substr_count($relativePath, '/') + 1;
        if ($depth > self::MAX_DEPTH) {
            throw new \InvalidArgumentException('Path too deep');
        }
    }

    private function sanitizeSegment(string $segment): string
    {
        $segment = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $segment) ?? '';
        if ($segment === '' || $segment === '_' || $segment === '.') {
            $segment = preg_replace('/[^A-Za-z0-9._-]+/', '_', $segment) ?? '';
        }
        // Ne pas trim les « _ » en tête : noms type _MG_3100.jpg (appareils photo).
        $segment = trim($segment, '.');
        if (strlen($segment) > self::MAX_SEGMENT_LEN) {
            $segment = substr($segment, 0, self::MAX_SEGMENT_LEN);
        }

        return $segment;
    }
}
