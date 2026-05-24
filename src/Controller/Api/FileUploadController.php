<?php

namespace App\Controller\Api;

use App\Service\AdminMediaStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
class FileUploadController extends AbstractController
{
    public function __construct(
        private readonly AdminMediaStorage $mediaStorage,
    ) {
    }

    #[Route(
        '/file/upload',
        name: 'file_upload',
        defaults: ['_format' => 'json'],
        methods: ['POST']
    )]
    public function fileUpload(Request $request): JsonResponse
    {
        $uploadedFile = $request->files->get('upload');

        if (!$uploadedFile) {
            return new JsonResponse(['error' => ['message' => 'No file uploaded']], Response::HTTP_BAD_REQUEST);
        }

        $safeName = $this->mediaStorage->sanitizeFileName((string) $uploadedFile->getClientOriginalName());
        $targetAbs = $this->mediaStorage->getRootFs() . DIRECTORY_SEPARATOR . $safeName;
        $targetAbs = $this->mediaStorage->dedupeFileAbsolutePath($targetAbs);
        $parentDir = dirname($targetAbs);

        if (!is_dir($parentDir) && !@mkdir($parentDir, 0775, true) && !is_dir($parentDir)) {
            return new JsonResponse(['error' => ['message' => 'Could not create upload directory']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $uploadedFile->move($parentDir, basename($targetAbs));
        } catch (\Exception $exception) {
            return new JsonResponse(['error' => ['message' => 'Could not save file: '.$exception->getMessage()]], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $relPath = $this->mediaStorage->relativePathFromAbsolute($targetAbs);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['error' => ['message' => 'Stored outside media root']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $url = $request->getSchemeAndHttpHost().'/'.$this->mediaStorage->webRelativeUrl($relPath);

        return new JsonResponse(['url' => $url]);
    }
}
