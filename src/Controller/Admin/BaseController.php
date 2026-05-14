<?php

namespace App\Controller\Admin;

use App\Entity\Interface\ActivableInterface;
use App\Entity\Interface\PositionableInterface;
use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
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

        $tw = $data['template_width'] ?? 10;
        if ($tw === '' || $tw === null) {
            $section->setTemplateWidth(10);
        } else {
            $v = filter_var($tw, FILTER_VALIDATE_INT);
            if (false === $v || $v < 1 || $v > 12) {
                return new JsonResponse(['status' => 'invalid template_width'], 400);
            }

            $section->setTemplateWidth($v);
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
        if ($main === null || $main->getType() !== 'liste') {
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

}
