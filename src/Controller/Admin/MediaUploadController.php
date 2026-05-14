<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\AdminMediaStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/{_locale}/admin/media-upload', requirements: ['_locale' => 'fr|en'], defaults: ['_locale' => 'fr'])]
#[IsGranted('ROLE_ADMIN')]
final class MediaUploadController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'application/pdf',
        'video/mp4',
    ];

    #[Route('/mkdir', name: 'admin_media_upload_mkdir', methods: ['POST'])]
    public function mkdir(Request $request, AdminMediaStorage $storage): Response
    {
        if (!$this->isCsrfTokenValid('media_upload', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.invalid_csrf'));

            return $this->redirectToRoute('admin_media_upload_index', ['_locale' => $request->getLocale()]);
        }

        $parent = (string) $request->request->get('parent', '');
        $name = (string) $request->request->get('name', '');

        try {
            $newPath = $storage->createSubdirectory($parent, $name);
            $this->addFlash('success', $this->translator->trans('admin.media_upload.flash.folder_created', ['%name%' => basename($newPath)]));

            return $this->redirectToRoute('admin_media_upload_index', ['_locale' => $request->getLocale(), 'path' => $newPath]);
        } catch (\InvalidArgumentException) {
            $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.invalid_folder_name'));
        } catch (\RuntimeException) {
            $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.folder_exists'));
        }

        $params = ['_locale' => $request->getLocale()];
        if ($parent !== '') {
            $params['path'] = $parent;
        }

        return $this->redirectToRoute('admin_media_upload_index', $params);
    }

    #[Route('/rename-folder', name: 'admin_media_upload_rename_folder', methods: ['POST'])]
    public function renameFolder(Request $request, AdminMediaStorage $storage): Response
    {
        if (!$this->isCsrfTokenValid('media_upload', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.invalid_csrf'));

            return $this->redirectToRoute('admin_media_upload_index', ['_locale' => $request->getLocale()]);
        }

        $oldPath = (string) $request->request->get('old_path', '');
        $newName = (string) $request->request->get('new_name', '');
        $currentBrowse = (string) $request->request->get('browse_path', '');

        try {
            $oldPath = $storage->normalizeRelativePath($oldPath);
            $currentBrowse = $storage->normalizeRelativePath($currentBrowse);
        } catch (\InvalidArgumentException) {
            $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.invalid_path'));

            return $this->redirectToRoute('admin_media_upload_index', ['_locale' => $request->getLocale()]);
        }

        try {
            $newPath = $storage->renameDirectory($oldPath, $newName);
            $this->addFlash('success', $this->translator->trans('admin.media_upload.flash.folder_renamed', ['%name%' => basename($newPath)]));
            $next = $storage->listingPathAfterRename($currentBrowse, $oldPath, $newPath);

            return $this->redirectToRoute('admin_media_upload_index', ['_locale' => $request->getLocale(), 'path' => $next]);
        } catch (\InvalidArgumentException) {
            $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.invalid_folder_name'));
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === 'Target exists') {
                $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.folder_exists'));
            } elseif ($msg === 'Not a directory') {
                $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.rename_not_dir'));
            } else {
                $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.rename_failed'));
            }
        }

        return $this->redirectToRoute('admin_media_upload_index', ['_locale' => $request->getLocale(), 'path' => $currentBrowse]);
    }

    #[Route('/delete-folder', name: 'admin_media_upload_delete_folder', methods: ['POST'])]
    public function deleteFolder(Request $request, AdminMediaStorage $storage): Response
    {
        if (!$this->isCsrfTokenValid('media_upload', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.invalid_csrf'));

            return $this->redirectToRoute('admin_media_upload_index', ['_locale' => $request->getLocale()]);
        }

        $path = (string) $request->request->get('path', '');
        $currentBrowse = (string) $request->request->get('browse_path', '');

        try {
            $path = $storage->normalizeRelativePath($path);
            $currentBrowse = $storage->normalizeRelativePath($currentBrowse);
        } catch (\InvalidArgumentException) {
            $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.invalid_path'));

            return $this->redirectToRoute('admin_media_upload_index', ['_locale' => $request->getLocale()]);
        }

        try {
            $storage->deleteDirectory($path);
            $this->addFlash('success', $this->translator->trans('admin.media_upload.flash.folder_deleted', ['%name%' => basename($path)]));
            $next = $storage->listingPathAfterDelete($currentBrowse, $path);

            return $this->redirectToRoute('admin_media_upload_index', ['_locale' => $request->getLocale(), 'path' => $next]);
        } catch (\InvalidArgumentException) {
            $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.delete_root_forbidden'));
        } catch (\RuntimeException) {
            $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.delete_failed'));
        }

        return $this->redirectToRoute('admin_media_upload_index', ['_locale' => $request->getLocale(), 'path' => $currentBrowse]);
    }

    #[Route('/delete-file', name: 'admin_media_upload_delete_file', methods: ['POST'])]
    public function deleteFile(Request $request, AdminMediaStorage $storage): Response
    {
        if (!$this->isCsrfTokenValid('media_upload', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.invalid_csrf'));

            return $this->redirectToRoute('admin_media_upload_index', ['_locale' => $request->getLocale()]);
        }

        $path = (string) $request->request->get('path', '');
        $currentBrowse = (string) $request->request->get('browse_path', '');

        try {
            $path = $storage->normalizeRelativePath($path);
            $currentBrowse = $storage->normalizeRelativePath($currentBrowse);
        } catch (\InvalidArgumentException) {
            $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.invalid_path'));

            return $this->redirectToRoute('admin_media_upload_index', ['_locale' => $request->getLocale()]);
        }

        try {
            $storage->deleteFile($path);
            $this->addFlash('success', $this->translator->trans('admin.media_upload.flash.file_deleted', ['%name%' => basename($path)]));

            return $this->redirectToRoute('admin_media_upload_index', ['_locale' => $request->getLocale(), 'path' => $currentBrowse]);
        } catch (\InvalidArgumentException) {
            $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.delete_file_outside'));
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === 'Not a file') {
                $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.delete_file_not_file'));
            } elseif ($msg === 'File not found') {
                $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.delete_file_missing'));
            } else {
                $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.delete_file_failed'));
            }
        }

        return $this->redirectToRoute('admin_media_upload_index', ['_locale' => $request->getLocale(), 'path' => $currentBrowse]);
    }

    #[Route('', name: 'admin_media_upload_index', methods: ['GET'])]
    public function index(
        Request $request,
        AdminMediaStorage $storage,
        #[Autowire('%env(APP_UPLOAD_MAX_SIZE)%')] string $uploadMaxSize,
    ): Response {
        $rawPath = (string) $request->query->get('path', '');
        try {
            $currentPath = $storage->normalizeRelativePath($rawPath);
        } catch (\InvalidArgumentException) {
            $this->addFlash('warning', $this->translator->trans('admin.media_upload.flash.invalid_path'));

            return $this->redirectToRoute('admin_media_upload_index', ['_locale' => $request->getLocale()]);
        }

        $rootFs = $storage->getRootFs();
        $uploadDirExists = is_dir($rootFs) && is_writable($rootFs);
        $list = ['directories' => [], 'files' => []];
        if ($uploadDirExists) {
            try {
                $list = $storage->listDirectory($currentPath);
            } catch (\RuntimeException) {
                $this->addFlash('danger', $this->translator->trans('admin.media_upload.flash.list_failed'));

                return $this->redirectToRoute('admin_media_upload_index', ['_locale' => $request->getLocale()]);
            }
        }

        return $this->render('admin/media_upload/index.html.twig', [
            'max_upload_bytes' => self::effectiveMaxBytes($uploadMaxSize),
            'allowed_mime_types' => self::ALLOWED_MIME_TYPES,
            'upload_dir_exists' => $uploadDirExists,
            'current_path' => $currentPath,
            'web_base' => $storage->getWebBasePath(),
            'directories' => $list['directories'],
            'files' => $list['files'],
            'php_ini_upload_max_filesize' => (string) ini_get('upload_max_filesize'),
            'php_ini_post_max_size' => (string) ini_get('post_max_size'),
            'app_upload_max_size' => trim($uploadMaxSize),
        ]);
    }

    #[Route('', name: 'admin_media_upload_api', methods: ['POST'])]
    public function upload(
        Request $request,
        AdminMediaStorage $storage,
        #[Autowire('%env(APP_UPLOAD_MAX_SIZE)%')] string $uploadMaxSize,
    ): JsonResponse {
        $token = $request->headers->get('X-CSRF-TOKEN') ?? $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('media_upload', $token)) {
            return new JsonResponse(['message' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        // Libère le verrou session : sans cela, les envois XHR parallèles se bloquent mutuellement
        // (un seul script tient la session PHP à la fois → 0 fichier terminé, progression partielle).
        if (\PHP_SESSION_ACTIVE === \session_status()) {
            if ($request->hasSession()) {
                $request->getSession()->save();
            }
            \session_write_close();
        }

        $rootFs = $storage->getRootFs();
        if (!is_dir($rootFs)) {
            if (!@mkdir($rootFs, 0775, true) && !is_dir($rootFs)) {
                return new JsonResponse(['message' => 'Cannot create upload directory'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        if (!is_writable($rootFs)) {
            return new JsonResponse(['message' => 'Upload directory is not writable'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['message' => 'No file'], Response::HTTP_BAD_REQUEST);
        }

        if (!$file->isValid()) {
            return new JsonResponse(['message' => $file->getErrorMessage()], Response::HTTP_BAD_REQUEST);
        }

        $maxBytes = self::effectiveMaxBytes($uploadMaxSize);
        if ($file->getSize() > $maxBytes) {
            return new JsonResponse(['message' => 'File too large'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $mime = strtolower((string) ($file->getMimeType() ?? ''));
        $mime = explode(';', $mime, 2)[0];
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            return new JsonResponse(['message' => 'File type not allowed'], Response::HTTP_BAD_REQUEST);
        }

        $uploadBasePath = (string) $request->request->get('upload_base_path', '');
        $fileRelativePath = (string) $request->request->get('file_relative_path', '');

        try {
            $uploadBasePath = $storage->normalizeRelativePath($uploadBasePath);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['message' => 'Invalid upload base path'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $targetAbs = $storage->resolveUploadTargetAbsolute(
                $uploadBasePath,
                $fileRelativePath,
                (string) $file->getClientOriginalName(),
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $rootReal = realpath($rootFs);
        if ($rootReal === false) {
            return new JsonResponse(['message' => 'Upload directory not found'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $rootNorm = rtrim(str_replace('\\', '/', $rootReal), '/');
        $targetNorm = str_replace('\\', '/', $targetAbs);
        $parentNorm = str_replace('\\', '/', dirname($targetAbs));

        $underRoot = static fn (string $path): bool => $path === $rootNorm || str_starts_with($path, $rootNorm . '/');
        if (!$underRoot($targetNorm) || !$underRoot($parentNorm)) {
            return new JsonResponse(['message' => 'Invalid target path'], Response::HTTP_BAD_REQUEST);
        }

        $targetAbs = $storage->dedupeFileAbsolutePath($targetAbs);
        $parentDir = dirname($targetAbs);
        if (!is_dir($parentDir) && !@mkdir($parentDir, 0775, true) && !is_dir($parentDir)) {
            return new JsonResponse(['message' => 'Cannot create subdirectory'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $file->move($parentDir, basename($targetAbs));
        } catch (\Throwable) {
            return new JsonResponse(['message' => 'Could not store file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $savedPath = $parentDir . DIRECTORY_SEPARATOR . basename($targetAbs);
        try {
            $relPath = $storage->relativePathFromAbsolute($savedPath);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['message' => 'Stored outside media root'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $publicUrl = '/' . $storage->webRelativeUrl($relPath);

        return new JsonResponse([
            'success' => true,
            'filename' => basename($targetAbs),
            'relativePath' => $relPath,
            'url' => $publicUrl,
        ]);
    }

    private static function effectiveMaxBytes(string $envSpec): int
    {
        $parsed = self::parsePhpIniSize(trim($envSpec));
        $iniUpload = self::parsePhpIniSize((string) ini_get('upload_max_filesize'));
        $iniPost = self::parsePhpIniSize((string) ini_get('post_max_size'));
        $candidates = array_filter([$parsed, $iniUpload, $iniPost], static fn (int $v): bool => $v > 0);
        if ($candidates === []) {
            return 10 * 1024 * 1024;
        }

        return min($candidates);
    }

    private static function parsePhpIniSize(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        if (preg_match('/^(\d+)$/', $value, $m)) {
            return (int) $m[1];
        }

        if (!preg_match('/^(\d+)([KMG]?)$/i', $value, $m)) {
            return 0;
        }

        $n = (int) $m[1];
        $unit = strtoupper($m[2] ?? '');

        return match ($unit) {
            'G' => $n * 1024 * 1024 * 1024,
            'M' => $n * 1024 * 1024,
            'K' => $n * 1024,
            default => $n,
        };
    }
}
