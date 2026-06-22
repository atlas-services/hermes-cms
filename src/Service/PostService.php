<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use App\Form\PostType;
use App\Repository\PostRepository;
use App\Repository\TemplateRepository;
use App\Service\FooterSectionService;
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
        if ($post->getLocale() === null || $post->getLocale() === '') {
            $post->setLocale($section->getEffectiveLocale());
        }

        $next = $this->postRepository->getMaxPositionInSection($section) + 1;
        $post->setPosition($next);
        if (!$section->getPosts()->contains($post)) {
            $section->addPost($post);
        }

        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }


    public function createFromMenu(Post $post, Menu $menu, ?Template $template): Post
    {
        $section = $this->createSectionFromMenu($menu, $template);

        return $this->create($post, $section);
    }

    public function createSectionFromMenu(Menu $menu, ?Template $template): Section
    {
        if (!$menu->isLeaf()) {
            throw new \DomainException('Impossible d’ajouter un post : le menu possède des sous-menus.');
        }

        $template = $template ?? ($menu->getSections()->isEmpty() ? null : $menu->getSections()->first()->getTemplate());

        if ($template !== null && \in_array(strtolower(trim((string) $template->getCode())), [FooterSectionService::TEMPLATE_CODE, TopbarSectionService::TEMPLATE_CODE], true)) {
            throw new \DomainException('Les gabarits globaux se gèrent depuis leur écran dédié, pas depuis une page menu.');
        }

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

        $menu->addSection($section);
        $this->em->persist($section);
        $this->em->flush();

        return $section;
    }

    // -------------------------
    // READ
    // -------------------------
    public function find(int $id): ?Post
    {
        return $this->postRepository->find($id);
    }

    /**
     * @return Post[]
     */
    public function findAll(): array
    {
        return $this->postRepository->findAll();
    }

    /**
     * @return Post[]
     */
    public function findAllOrdered(): array
    {
        return $this->postRepository->findAllOrdered();
    }

    /**
     * @return Post[]
     */
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
        $post->setPosition($this->postRepository->getMaxPositionInSection($targetSection) + 1);
        if (!$targetSection->getPosts()->contains($post)) {
            $targetSection->addPost($post);
        }

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

    public function deleteSection(Section $section): void
    {
        $this->em->remove($section);
        $this->em->flush();
    }

    /**
     * Supprime tous les posts d’une section « liste » ; la section est conservée (contrairement à {@see delete} sur le dernier post).
     *
     * @return int nombre de posts supprimés
     */
    public function deleteAllPostsInListeSection(Section $section): int
    {
        $tpl = $section->getTemplate();
        if ($tpl === null || $tpl->getType() !== PostType::TEMPLATE_TYPE_LISTE) {
            throw new \DomainException('Section template must be "liste".');
        }

        $posts = $section->getPosts()->toArray();
        foreach ($posts as $post) {
            $section->removePost($post);
            $this->em->remove($post);
        }
        $this->em->flush();

        return count($posts);
    }

    // -------------------------
    // MÉTIER
    // -------------------------
    private function assertSectionIsValid(Section $section): void
    {
        if ($section->getTemplate() === null) {
            throw new \DomainException('La section doit avoir un template.');
        }

        if ($section->isGlobalSection()) {
            if ($section->getMenu() !== null) {
                throw new \DomainException('Une section globale ne doit pas être rattachée à une page menu.');
            }

            return;
        }

        $menu = $section->getMenu();

        if (!$menu) {
            throw new \DomainException('La section doit être liée à un menu.');
        }

        if (!$menu->isLeaf()) {
            throw new \DomainException('Impossible d’ajouter un post : le menu possède des enfants.');
        }
    }
}
