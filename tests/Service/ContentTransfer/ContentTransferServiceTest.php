<?php

declare(strict_types=1);

namespace App\Tests\Service\ContentTransfer;

use App\DataFixtures\PostFixtures;
use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use App\Repository\MenuRepository;
use App\Service\ContentTransfer\ContentTransferService;
use App\Tests\Base\BaseKernelTestCase;

final class ContentTransferServiceTest extends BaseKernelTestCase
{
    private ContentTransferService $transferService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transferService = static::getContainer()->get(ContentTransferService::class);
    }

    protected function loadFixtures(): array
    {
        return [new PostFixtures()];
    }

    public function testCopySectionToAnotherLocalePage(): void
    {
        $em = $this->em;
        $menuRepo = static::getContainer()->get(MenuRepository::class);
        $sourceMenu = $menuRepo->findOneBy(['name' => 'Posts Menu', 'locale' => 'fr']);
        $this->assertInstanceOf(Menu::class, $sourceMenu);
        $sourceSection = $sourceMenu->getSections()->first();
        $this->assertInstanceOf(Section::class, $sourceSection);

        $targetMenu = new Menu();
        $targetMenu->setName('EN target page');
        $targetMenu->setLocale('en');
        $targetMenu->setReferenceName('en-target-page');
        $targetMenu->setPosition(99);
        $em->persist($targetMenu);

        $targetSectionShell = new Section();
        $targetSectionShell->setTemplate($sourceSection->getTemplate());
        $targetSectionShell->setPosition(1);
        $targetMenu->addSection($targetSectionShell);
        $em->flush();

        $countBefore = $targetMenu->getSections()->count();
        $postsBefore = $sourceSection->getPosts()->count();

        $this->transferService->copySection($sourceSection, $targetMenu);

        $em->refresh($targetMenu);
        self::assertSame($countBefore + 1, $targetMenu->getSections()->count());
        $newSection = $targetMenu->getSections()->last();
        self::assertInstanceOf(Section::class, $newSection);
        self::assertSame($postsBefore, $newSection->getPosts()->count());
        $firstPost = $newSection->getPosts()->first();
        self::assertInstanceOf(Post::class, $firstPost);
        self::assertSame('en', $firstPost->getLocale());
    }

    public function testMovePostToAnotherSection(): void
    {
        $em = $this->em;
        $menuRepo = static::getContainer()->get(MenuRepository::class);
        $menu = $menuRepo->findOneBy(['name' => 'Posts Menu']);
        $this->assertInstanceOf(Menu::class, $menu);

        $template = $em->getRepository(Template::class)->findOneBy(['code' => 'libre']);
        $this->assertInstanceOf(Template::class, $template);

        $targetSection = new Section();
        $targetSection->setMenu($menu);
        $targetSection->setTemplate($template);
        $targetSection->setPosition(99);
        $em->persist($targetSection);

        $post = $em->getRepository(Post::class)->findOneBy(['name' => 'Post 1']);
        $this->assertInstanceOf(Post::class, $post);

        $this->transferService->movePost($post, $targetSection);

        self::assertSame($targetSection, $post->getSection());
    }
}
