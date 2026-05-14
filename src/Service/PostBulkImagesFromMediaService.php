<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Post;
use App\Entity\Section;
use App\Form\PostType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Crée des posts (template « liste ») à partir d’images déjà présentes sous la racine média admin
 * ({@see AdminMediaStorage}, même arbre que l’écran « envoi de fichiers »).
 */
final class PostBulkImagesFromMediaService
{
    private const array IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct(
        private readonly AdminMediaStorage $mediaStorage,
        private readonly EntityManagerInterface $em,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * @return list<string> chemins relatifs fichiers image (tri naturel par nom de fichier)
     */
    public function listFlatImageFilesInDirectory(string $relativePath): array
    {
        $relativePath = $this->mediaStorage->normalizeRelativePath($relativePath);
        $listing = $this->mediaStorage->listDirectory($relativePath);
        $out = [];
        foreach ($listing['files'] as $row) {
            $ext = strtolower((string) pathinfo($row['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, self::IMAGE_EXTENSIONS, true)) {
                continue;
            }
            $out[] = $row['path'];
        }
        usort($out, static fn (string $a, string $b): int => strnatcasecmp(
            basename($a),
            basename($b),
        ));

        return $out;
    }

    /**
     * Crée un post par fichier image (dossier plat uniquement, pas d’exploration récursive).
     *
     * @throws \DomainException
     */
    public function importFlatImageDirectory(Section $section, string $mediaRelativePath, string $locale): int
    {
        $this->assertListeSection($section);

        $mediaRelativePath = $this->mediaStorage->normalizeRelativePath($mediaRelativePath);
        $fullDir = $this->mediaStorage->filesystemPath($mediaRelativePath);
        if (!is_dir($fullDir) || !is_readable($fullDir)) {
            throw new \DomainException('Invalid or unreadable media directory.');
        }

        $files = $this->listFlatImageFilesInDirectory($mediaRelativePath);
        if ($files === []) {
            throw new \DomainException('No importable images in this folder (JPEG, PNG, GIF, WebP).');
        }

        $prefix = $this->buildNamePrefix($mediaRelativePath);
        $existingNames = [];
        foreach ($section->getPosts() as $p) {
            $existingNames[strtolower((string) $p->getName())] = true;
        }

        $position = $section->getPosts()->count();
        $created = 0;
        $tmpFiles = [];
        $filesystem = new Filesystem();

        $conn = $this->em->getConnection();
        $conn->beginTransaction();
        try {
            foreach ($files as $index => $relFile) {
                ++$position;
                $postName = $prefix . '_' . ($index + 1);
                if (isset($existingNames[strtolower($postName)])) {
                    throw new \DomainException(sprintf('A post named "%s" already exists in this section.', $postName));
                }
                $existingNames[strtolower($postName)] = true;

                $abs = $this->mediaStorage->filesystemPath($relFile);
                if (!is_file($abs) || !is_readable($abs)) {
                    throw new \DomainException(sprintf('File not readable: %s', $relFile));
                }

                $ext = strtolower((string) pathinfo($relFile, PATHINFO_EXTENSION));
                if ($ext === '') {
                    throw new \DomainException(sprintf('Missing extension: %s', $relFile));
                }
                $tmpPath = sprintf(
                    '%s/hermes-bulk-%d-%s.%s',
                    sys_get_temp_dir(),
                    (int) $section->getId(),
                    bin2hex(random_bytes(8)),
                    $ext,
                );
                $filesystem->copy($abs, $tmpPath, true);
                $tmpFiles[] = $tmpPath;

                $originalName = basename(str_replace('\\', '/', $relFile));
                $mimeType = @mime_content_type($tmpPath) ?: null;

                $post = new Post();
                $post->setSection($section);
                $post->setName($postName);
                $post->setLocale($locale);
                $post->setContent('');
                $post->setActive(true);
                $post->setPosition($position);
                // Vich n’upload que pour UploadedFile (voir UploadHandler::hasUploadedFile) ; test=true = fichier local hors HTTP.
                $post->setImageFile(new UploadedFile($tmpPath, $originalName, $mimeType, null, true));

                $this->em->persist($post);
                ++$created;
            }

            $this->em->flush();
            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            throw $e;
        } finally {
            foreach ($tmpFiles as $t) {
                if (is_file($t)) {
                    $filesystem->remove($t);
                }
            }
        }

        return $created;
    }

    private function assertListeSection(Section $section): void
    {
        $tpl = $section->getTemplate();
        if ($tpl === null || $tpl->getType() !== PostType::TEMPLATE_TYPE_LISTE) {
            throw new \DomainException('Section template must be "liste".');
        }
    }

    private function buildNamePrefix(string $normalizedRelativePath): string
    {
        $basename = $normalizedRelativePath === ''
            ? 'images'
            : basename(str_replace('\\', '/', $normalizedRelativePath));

        $ascii = $this->slugger->slug($basename, '_')->lower()->toString();
        $ascii = (string) preg_replace('/[^a-z0-9_]+/', '_', $ascii);
        $ascii = trim($ascii, '_');
        if ($ascii === '') {
            $ascii = 'images';
        }

        return substr($ascii, 0, 80);
    }
}
