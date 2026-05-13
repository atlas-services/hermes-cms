<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use App\Repository\PostRepository;
use App\Repository\TemplateRepository;
use Doctrine\ORM\EntityManagerInterface;

class PostService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PostRepository $postRepository,
        private TemplateRepository $templateRepository,
    ) {}

    // -------------------------
    // CREATE
    // -------------------------
    public function create(Post $post, Section $section): Post
    {
        $this->assertSectionIsValid($section);

        $post->setSection($section);

        // position auto (optionnel mais recommandé)
        $post->setPosition($section->getPosts()->count() + 1);

        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }


    public function createFromMenu(Post $post, Menu $menu, ?Template $template): Post
    {
        if (!$menu->isLeaf()) {
            throw new \DomainException('Le menu doit être une feuille.');
        }

        $template = $template ?? ($menu->getSections()->isEmpty() ? null : $menu->getSections()->first()->getTemplate());

        if (!$template) {
            $template = $this->em->getRepository(Template::class)->findOneBy(['code' => 'libre']);
            if (!$template) {
                throw new \DomainException('Template par défaut introuvable.');
            }
        }

        $section = new Section();
        $section->setMenu($menu);
        $section->setTemplate($template);
        $section->setTemplateWidth(10);
        $defaultModale = $this->templateRepository->findDefaultModaleTemplate();
        if ($defaultModale !== null) {
            $section->setTemplate2($defaultModale);
        }

        $maxPosition = 0;
        foreach ($menu->getSections() as $existingSection) {
            $maxPosition = max($maxPosition, $existingSection->getPosition());
        }
        $section->setPosition($maxPosition + 1);

        $this->em->persist($section);

        return $this->create($post, $section);
    }

    // -------------------------
    // READ
    // -------------------------
    public function find(int $id): ?Post
    {
        return $this->postRepository->find($id);
    }

    public function findAll(): array
    {
        return $this->postRepository->findAll();
    }

    public function findAllOrdered(): array
    {
        return $this->postRepository->findAllOrdered();
    }

    public function findBySection(Section $section): array
    {
        return $this->postRepository->findBy(
            ['section' => $section],
            ['position' => 'ASC']
        );
    }

    // -------------------------
    // UPDATE
    // -------------------------
    public function update(Post $post, ?Section $newSection = null): Post
    {
        if ($newSection !== null) {
            $this->assertSectionIsValid($newSection);
            $post->setSection($newSection);
        }

        $this->em->flush();

        return $post;
    }

    public function move(Post $post, Section $targetSection): Post
    {
        $this->assertSectionIsValid($targetSection);

        $oldSection = $post->getSection();
        if ($oldSection !== null && $oldSection !== $targetSection) {
            $oldSection->removePost($post);
        }

        $post->setSection($targetSection);
        $post->setPosition($targetSection->getPosts()->count() + 1);

        $this->em->flush();

        if ($oldSection !== null && $oldSection->getPosts()->isEmpty()) {
            $this->em->remove($oldSection);
            $this->em->flush();
        }

        return $post;
    }

    // -------------------------
    // DELETE
    // -------------------------
    public function delete(Post $post): void
    {
        $section = $post->getSection();

        if ($section !== null) {
            $section->removePost($post);
        }

        $this->em->remove($post);
        $this->em->flush();

        if ($section !== null && $section->getPosts()->isEmpty()) {
            $this->em->remove($section);
            $this->em->flush();
        }
    }

    // -------------------------
    // MÉTIER
    // -------------------------
    private function assertSectionIsValid(Section $section): void
    {
        $menu = $section->getMenu();

        if (!$menu) {
            throw new \DomainException('La section doit être liée à un menu.');
        }

        if (!$menu->isLeaf()) {
            throw new \DomainException('Impossible d’ajouter un post : le menu possède des enfants.');
        }

        if ($section->getTemplate() === null) {
            throw new \DomainException('La section doit avoir un template.');
        }
    }
}
