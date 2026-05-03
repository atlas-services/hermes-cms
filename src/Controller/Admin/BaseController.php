<?php

namespace App\Controller\Admin;

use App\Entity\Interface\ActivableInterface;
use App\Entity\Interface\PositionableInterface;
use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
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

}
