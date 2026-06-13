<?php

namespace App\Controller\Admin;

use App\Entity\Interface\ActivableInterface;
use App\Entity\Interface\PositionableInterface;
use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use App\Service\AppLocaleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/admin', defaults: ['_locale' => 'fr'], requirements: ['_locale' => 'fr|en'],)]
class BaseController extends AbstractController
{

    #[Route('/update-positions', name: 'app_update_positions', methods: ['POST'])]
    public function updatePositions(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {

        /** @var array{type: string, positions?: array<int, array{id:int, position:int}>, id?: int} $data */
        $data = json_decode(
            $request->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $entityClass = match ($data['type']) {
            'menu' => Menu::class,
            'post' => Post::class,
            'section' => Section::class,
            default => throw $this->createNotFoundException('Invalid type'),
        };

        $repository = $entityManager->getRepository($entityClass);

        foreach ($data['positions'] as $positionData) {
            /** @var PositionableInterface|null $item */
            $item = $repository->find($positionData['id']);

            if (!$item instanceof PositionableInterface) {
                continue;
            }

            $item->setPosition($positionData['position']);
            $entityManager->persist($item);
        }

        $entityManager->flush();

        return new JsonResponse(['status' => 'success']);
    }

    #[Route('/switch-active', name: 'app_switch_active', methods: ['POST'])]
    public function updatswitchActive(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var array{type: string, positions?: array<int, array{id:int, position:int}>, id?: int} $data */
        $data = json_decode(
            $request->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $entityClass = match ($data['type']) {
            'menu' => Menu::class,
            'post' => Post::class,
            'section' => Section::class,
            default => throw $this->createNotFoundException('Invalid type'),
        };
        /** @var ActivableInterface|null $item */
        $item = $entityManager->getRepository($entityClass)->find($data['id']);

        if ($item === null) {
            return new JsonResponse(['status' => 'not found'], 404);
        }

        $item->setActive(!$item->isActive());
        $entityManager->persist($item);

        $entityManager->flush();

        return new JsonResponse(['status' => 'success']);
    }

    #[Route('/update-template-width', name: 'app_update_template_width', methods: ['POST'])]
    public function updateTemplateWidth(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            /** @var array{type?: string, id?: mixed, template_width?: mixed} $data */
            $data = json_decode(
                $request->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            return new JsonResponse(['status' => 'invalid json'], 400);
        }

        if (($data['type'] ?? '') !== 'section') {
            return new JsonResponse(['status' => 'invalid type'], 400);
        }

        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (false === $id || $id < 1) {
            return new JsonResponse(['status' => 'invalid id'], 400);
        }

        $section = $entityManager->find(Section::class, $id);
        if (!$section instanceof Section) {
            return new JsonResponse(['status' => 'not found'], 404);
        }

        $rawTw = $data['template_width'] ?? null;
        if ($rawTw === null || $rawTw === '') {
            $section->setTemplateWidth(10);
        } else {
            $v = filter_var($rawTw, FILTER_VALIDATE_INT);
            if (false === $v || $v < 1 || $v > 12) {
                return new JsonResponse(['status' => 'invalid template_width'], 400);
            }

            $section->setTemplateWidth($v);
        }

        $entityManager->flush();

        return new JsonResponse(['status' => 'success']);
    }

    #[Route('/update-template2-width', name: 'app_update_template2_width', methods: ['POST'])]
    public function updateTemplate2Width(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            /** @var array{type?: string, id?: mixed, template2_width?: mixed} $data */
            $data = json_decode(
                $request->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            return new JsonResponse(['status' => 'invalid json'], 400);
        }

        if (($data['type'] ?? '') !== 'section') {
            return new JsonResponse(['status' => 'invalid type'], 400);
        }

        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (false === $id || $id < 1) {
            return new JsonResponse(['status' => 'invalid id'], 400);
        }

        $section = $entityManager->find(Section::class, $id);
        if (!$section instanceof Section) {
            return new JsonResponse(['status' => 'not found'], 404);
        }

        $main = $section->getTemplate();
        if ($main === null || trim((string) $main->getType()) !== 'liste') {
            return new JsonResponse(['status' => 'section template must be liste'], 400);
        }

        $tw = $data['template2_width'] ?? null;
        if ($tw === '' || $tw === null) {
            $section->setTemplate2Width(null);
        } else {
            $v = filter_var($tw, FILTER_VALIDATE_INT);
            if (false === $v || $v < 1 || $v > 12) {
                return new JsonResponse(['status' => 'invalid template2_width'], 400);
            }

            $section->setTemplate2Width($v);
        }

        $entityManager->flush();

        return new JsonResponse(['status' => 'success']);
    }

    #[Route('/update-section-template2', name: 'app_update_section_template2', methods: ['POST'])]
    public function updateSectionTemplate2(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            /** @var array{type?: string, id?: mixed, template2_code?: mixed} $data */
            $data = json_decode(
                $request->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            return new JsonResponse(['status' => 'invalid json'], 400);
        }

        if (($data['type'] ?? '') !== 'section') {
            return new JsonResponse(['status' => 'invalid type'], 400);
        }

        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (false === $id || $id < 1) {
            return new JsonResponse(['status' => 'invalid id'], 400);
        }

        $section = $entityManager->find(Section::class, $id);
        if (!$section instanceof Section) {
            return new JsonResponse(['status' => 'not found'], 404);
        }

        $main = $section->getTemplate();
        if ($main === null || trim((string) $main->getType()) !== 'liste') {
            return new JsonResponse(['status' => 'section template must be liste'], 400);
        }

        $raw = $data['template2_code'] ?? '';
        $code = trim((string) $raw);

        if ($code === '') {
            $section->setTemplate2(null);

            $entityManager->flush();

            return new JsonResponse(['status' => 'success']);
        }

        if ($code !== 'modale1' && $code !== 'modale2') {
            return new JsonResponse(['status' => 'invalid template2_code'], 400);
        }

        $template2 = $entityManager->getRepository(Template::class)->findOneBy(['code' => $code]);
        if (!$template2 instanceof Template) {
            return new JsonResponse(['status' => 'template2 not found'], 404);
        }

        $section->setTemplate2($template2);
        $entityManager->flush();

        return new JsonResponse(['status' => 'success']);
    }

    /**
     * Changement du template principal d’une section : uniquement entre gabarits de type « liste » (YAML).
     */
    #[Route('/update-section-liste-template', name: 'app_update_section_liste_template', methods: ['POST'])]
    public function updateSectionListeTemplate(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $section = $this->resolveSectionFromJson($request, $entityManager);
        if ($section instanceof JsonResponse) {
            return $section;
        }

        try {
            /** @var array{template_id?: mixed} $data */
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['status' => 'invalid json'], 400);
        }

        $current = $section->getTemplate();
        if ($current === null) {
            return new JsonResponse(['status' => 'section has no template'], 400);
        }

        if (trim((string) $current->getType()) !== 'liste') {
            return new JsonResponse(['status' => 'section template must be liste'], 400);
        }

        $templateId = filter_var($data['template_id'] ?? null, FILTER_VALIDATE_INT);
        if (false === $templateId || $templateId < 1) {
            return new JsonResponse(['status' => 'invalid template_id'], 400);
        }

        $new = $entityManager->find(Template::class, $templateId);
        if (!$new instanceof Template) {
            return new JsonResponse(['status' => 'template not found'], 404);
        }

        if (!$new->isActive()) {
            return new JsonResponse(['status' => 'template inactive'], 400);
        }

        if (trim((string) $new->getType()) !== 'liste') {
            return new JsonResponse(['status' => 'template must be liste'], 400);
        }

        $section->setTemplate($new);
        $entityManager->flush();

        return new JsonResponse(['status' => 'success']);
    }

    #[Route('/update-section-template-nb-col', name: 'app_update_section_template_nb_col', methods: ['POST'])]
    public function updateSectionTemplateNbCol(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $section = $this->resolveListeSectionFromJson($request, $entityManager);
        if ($section instanceof JsonResponse) {
            return $section;
        }

        try {
            /** @var array{template_nb_col?: mixed} $data */
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['status' => 'invalid json'], 400);
        }

        $v = filter_var($data['template_nb_col'] ?? null, FILTER_VALIDATE_INT);
        if (false === $v || $v < 1 || $v > 12) {
            return new JsonResponse(['status' => 'invalid template_nb_col'], 400);
        }

        $section->setTemplateNbCol($v);
        $entityManager->flush();

        return new JsonResponse(['status' => 'success']);
    }

    #[Route('/update-section-transparent', name: 'app_update_section_transparent', methods: ['POST'])]
    public function updateSectionTransparent(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $section = $this->resolveSectionFromJson($request, $entityManager);
        if ($section instanceof JsonResponse) {
            return $section;
        }

        try {
            /** @var array{transparent?: mixed} $data */
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['status' => 'invalid json'], 400);
        }

        $transparent = filter_var($data['transparent'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $section->setTransparent($transparent);
        $entityManager->flush();

        return new JsonResponse(['status' => 'success']);
    }

    #[Route('/update-section-template-bgcolor', name: 'app_update_section_template_bgcolor', methods: ['POST'])]
    public function updateSectionTemplateBgcolor(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $section = $this->resolveSectionFromJson($request, $entityManager);
        if ($section instanceof JsonResponse) {
            return $section;
        }

        try {
            /** @var array{template_bgcolor?: mixed} $data */
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['status' => 'invalid json'], 400);
        }

        $color = trim((string) ($data['template_bgcolor'] ?? ''));
        $section->setTemplateBgcolor($color === '' ? null : $color);
        $entityManager->flush();

        return new JsonResponse(['status' => 'success', 'transparent' => $section->isTransparent()]);
    }

    #[Route('/update-section-template-image-filter', name: 'app_update_section_template_image_filter', methods: ['POST'])]
    public function updateSectionTemplateImageFilter(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $section = $this->resolveListeSectionFromJson($request, $entityManager);
        if ($section instanceof JsonResponse) {
            return $section;
        }

        try {
            /** @var array{template_image_filter?: mixed} $data */
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['status' => 'invalid json'], 400);
        }

        $filter = trim((string) ($data['template_image_filter'] ?? ''));
        if ($filter === '') {
            $section->setTemplateImageFilter(null);
        } else {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $filter)) {
                return new JsonResponse(['status' => 'invalid template_image_filter'], 400);
            }
            $section->setTemplateImageFilter($filter);
        }

        $entityManager->flush();

        return new JsonResponse(['status' => 'success']);
    }

    #[Route('/update-section-locale', name: 'app_update_section_locale', methods: ['POST'])]
    public function updateSectionLocale(
        Request $request,
        EntityManagerInterface $entityManager,
        AppLocaleService $appLocaleService,
    ): JsonResponse {
        $section = $this->resolveSectionFromJson($request, $entityManager);
        if ($section instanceof JsonResponse) {
            return $section;
        }
        if (!$section->isFooterSection()) {
            return new JsonResponse(['status' => 'section is not footer'], 400);
        }

        try {
            /** @var array{locale?: mixed} $data */
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['status' => 'invalid json'], 400);
        }

        $locale = strtolower(trim((string) ($data['locale'] ?? '')));
        if (!\in_array($locale, $appLocaleService->getContentLocales(), true)) {
            return new JsonResponse(['status' => 'invalid locale'], 400);
        }

        $section->setLocale($locale);
        $entityManager->flush();

        return new JsonResponse(['status' => 'success']);
    }

    /**
     * @return Section|JsonResponse
     */
    private function resolveSectionFromJson(Request $request, EntityManagerInterface $entityManager): Section|JsonResponse
    {
        try {
            /** @var array{type?: string, id?: mixed} $data */
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['status' => 'invalid json'], 400);
        }

        if (($data['type'] ?? '') !== 'section') {
            return new JsonResponse(['status' => 'invalid type'], 400);
        }

        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (false === $id || $id < 1) {
            return new JsonResponse(['status' => 'invalid id'], 400);
        }

        $section = $entityManager->find(Section::class, $id);
        if (!$section instanceof Section) {
            return new JsonResponse(['status' => 'not found'], 404);
        }

        return $section;
    }

    /**
     * @return Section|JsonResponse
     */
    private function resolveListeSectionFromJson(Request $request, EntityManagerInterface $entityManager): Section|JsonResponse
    {
        $section = $this->resolveSectionFromJson($request, $entityManager);
        if ($section instanceof JsonResponse) {
            return $section;
        }

        $main = $section->getTemplate();
        if ($main === null || trim((string) $main->getType()) !== 'liste') {
            return new JsonResponse(['status' => 'section template must be liste'], 400);
        }

        return $section;
    }

}
