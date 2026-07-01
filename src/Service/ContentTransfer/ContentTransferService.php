<?php

declare(strict_types=1);

namespace App\Service\ContentTransfer;

use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use App\Repository\PostRepository;
use App\Service\FooterSectionService;
use App\Service\PostService;
use App\Service\TopbarSectionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Storage\StorageInterface;

final class ContentTransferService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PostService $postService,
        private readonly PostRepository $postRepository,
        private readonly ContentTransferTargetProvider $targetProvider,
        private readonly StorageInterface $vichStorage,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
        #[Autowire(param: 'hermes_path_content_images')]
        private readonly string $contentImagesWebPath,
    ) {
    }

    public function copySection(Section $source, Menu $targetMenu): Section
    {
        $this->assertSectionCanLeave($source);
        $this->assertTargetMenu($targetMenu);
        $this->assertNotFooterTemplate($source->getTemplate());

        $target = $this->cloneSection($source, $targetMenu);
        $target->setPosition($this->nextSectionPosition($targetMenu));
        $targetMenu->addSection($target);
        $this->em->persist($target);

        $locale = $targetMenu->getLocale() ?? 'fr';
        foreach ($source->getPosts() as $sourcePost) {
            $this->copyPostEntity($sourcePost, $target, $locale);
        }

        $this->em->flush();

        return $target;
    }

    public function moveSection(Section $source, Menu $targetMenu): Section
    {
        $this->assertSectionCanLeave($source);
        $this->assertTargetMenu($targetMenu);
        $this->assertNotFooterTemplate($source->getTemplate());

        if ($source->getMenu()?->getId() === $targetMenu->getId()) {
            throw new \DomainException('La section est déjà sur cette page.');
        }

        $locale = $targetMenu->getLocale() ?? 'fr';
        $source->setMenu($targetMenu);
        $source->setPosition($this->nextSectionPosition($targetMenu));

        foreach ($source->getPosts() as $post) {
            $post->setLocale($locale);
        }

        $this->em->flush();

        return $source;
    }

    public function copyPost(Post $source, Menu $targetMenu, ?Section $targetSection = null): Post
    {
        $this->assertTargetMenu($targetMenu);
        $sourceSection = $source->getSection();
        if ($sourceSection === null) {
            throw new \DomainException('Post sans section.');
        }
        $this->assertNotFooterTemplate($sourceSection->getTemplate());

        $targetSection ??= $this->createSectionForPostCopy($sourceSection, $targetMenu);
        $locale = $targetMenu->getLocale() ?? 'fr';

        $copy = $this->copyPostEntity($source, $targetSection, $locale);
        $this->em->flush();

        return $copy;
    }

    public function movePost(Post $source, Section $targetSection): Post
    {
        $post = $this->postService->move($source, $targetSection);
        $post->setLocale($targetSection->getEffectiveLocale());
        $this->em->flush();

        return $post;
    }

    public function resolveTargetMenu(int $menuId): Menu
    {
        $menu = $this->targetProvider->findTargetMenu($menuId);
        if ($menu === null) {
            throw new \DomainException('Page cible invalide (menu feuille avec contenu attendu).');
        }

        return $menu;
    }

    public function resolveTargetSection(Menu $targetMenu, ?int $sectionId, bool $createSectionIfMissing): Section
    {
        if ($sectionId !== null && $sectionId > 0) {
            $section = $this->targetProvider->findTargetSection($sectionId, $targetMenu);
            if ($section === null) {
                throw new \DomainException('Section cible introuvable sur cette page.');
            }

            return $section;
        }

        throw new \DomainException('Section cible requise.');
    }

    private function createSectionForPostCopy(Section $sourceSection, Menu $targetMenu): Section
    {
        $section = $this->cloneSection($sourceSection, $targetMenu);
        $section->setPosition($this->nextSectionPosition($targetMenu));
        $targetMenu->addSection($section);
        $this->em->persist($section);

        return $section;
    }

    private function cloneSection(Section $source, Menu $targetMenu): Section
    {
        $section = new Section();
        $section->setMenu($targetMenu);
        $section->setTemplate($source->getTemplate());
        $section->setTemplate2($source->getTemplate2());
        $section->setTemplateWidth($source->getTemplateWidth());
        $section->setTemplate2Width($source->getRawTemplate2Width());
        $section->setActive($source->isActive());
        $section->setTransparent($source->isTransparent());
        $section->setTemplateBgcolor($source->getRawTemplateBgcolor());
        $section->setTemplateColor($source->getRawTemplateColor());
        $section->setTemplateNbCol($source->getTemplateNbCol());
        $section->setTemplateImageFilter($source->getTemplateImageFilter());

        return $section;
    }

    private function copyPostEntity(Post $source, Section $targetSection, string $locale): Post
    {
        $post = new Post();
        $post->setName($this->uniquePostName($targetSection, (string) $source->getName()));
        $post->setContent($source->getContent());
        $post->setActive($source->isActive());
        $post->setStartPublishedAt($source->getStartPublishedAt());
        $post->setEndPublishedAt($source->getEndPublishedAt());
        $post->setLocale($locale);

        $this->postService->create($post, $targetSection);

        if ($source->getFileName()) {
            $this->duplicatePostImage($source, $post);
            $this->em->flush();
        }

        return $post;
    }

    private function duplicatePostImage(Post $source, Post $target): void
    {
        $resolved = $this->vichStorage->resolveUri($source, 'imageFile');
        if ($resolved === null || $resolved === '') {
            return;
        }

        $prefix = trim($this->contentImagesWebPath, '/');
        $relative = ltrim($resolved, '/');
        if (str_starts_with($relative, $prefix . '/')) {
            $relative = substr($relative, strlen($prefix) + 1);
        }

        $sourcePath = $this->projectDir . '/public/' . $prefix . '/' . $relative;
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            $target->setFileName($source->getFileName());

            return;
        }

        $ext = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'bin';
        $tmp = sys_get_temp_dir() . '/hermes-transfer-' . bin2hex(random_bytes(8)) . '.' . $ext;
        (new Filesystem())->copy($sourcePath, $tmp, true);

        $mime = @mime_content_type($tmp) ?: null;
        $target->setImageFile(new UploadedFile($tmp, basename($sourcePath), $mime, null, true));
        $this->em->flush();
        @unlink($tmp);
    }

    private function uniquePostName(Section $section, string $baseName): string
    {
        $baseName = trim($baseName);
        if ($baseName === '') {
            $baseName = 'post';
        }

        $candidate = $baseName;
        $suffix = 2;
        while ($this->postRepository->findOneBy(['section' => $section, 'name' => $candidate]) !== null) {
            $candidate = $baseName . ' (' . $suffix . ')';
            ++$suffix;
        }

        return $candidate;
    }

    private function nextSectionPosition(Menu $menu): int
    {
        $max = 0;
        foreach ($menu->getSections() as $section) {
            $max = max($max, $section->getPosition());
        }

        return $max + 1;
    }

    private function assertSectionCanLeave(Section $section): void
    {
        if ($section->isGlobalSection()) {
            throw new \DomainException('Les sections globales ne peuvent pas être copiées ou déplacées depuis cet écran.');
        }
    }

    private function assertTargetMenu(Menu $menu): void
    {
        if (!$menu->isPage()) {
            throw new \DomainException('La page cible doit être un menu avec au moins une section.');
        }
    }

    private function assertNotFooterTemplate(?Template $template): void
    {
        if ($template !== null && \in_array(strtolower(trim((string) $template->getCode())), [FooterSectionService::TEMPLATE_CODE, TopbarSectionService::TEMPLATE_CODE], true)) {
            throw new \DomainException('Les gabarits globaux se gèrent depuis leur admin dédié.');
        }
    }
}
