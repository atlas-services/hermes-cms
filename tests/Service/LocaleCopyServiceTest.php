<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DataFixtures\PostFixtures;
use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use App\Repository\MenuRepository;
use App\Service\FooterSectionService;
use App\Service\LocaleCopyService;
use App\Tests\Base\BaseKernelTestCase;

final class LocaleCopyServiceTest extends BaseKernelTestCase
{
    protected function loadFixtures(): array
    {
        return [new PostFixtures()];
    }

    public function testCopyLocaleDuplicatesMenusAndFooterByReferenceName(): void
    {
        $em = $this->em;
        /** @var LocaleCopyService $copy */
        $copy = static::getContainer()->get(LocaleCopyService::class);
        /** @var MenuRepository $menuRepo */
        $menuRepo = static::getContainer()->get(MenuRepository::class);

        $menu = $menuRepo->findOneBy(['name' => 'Posts Menu', 'locale' => 'fr']);
        $this->assertInstanceOf(Menu::class, $menu);
        $menu->setReferenceName('posts-menu');
        $em->flush();

        $footerTpl = $em->getRepository(Template::class)->findOneBy(['code' => FooterSectionService::TEMPLATE_CODE]);
        $this->assertInstanceOf(Template::class, $footerTpl);

        $footer = new Section();
        $footer->setMenu(null);
        $footer->setLocale('fr');
        $footer->setReferenceName('footer-main');
        $footer->setTemplate($footerTpl);
        $footer->setPosition(1);
        $footer->setActive(true);
        $em->persist($footer);

        $footerPost = new Post();
        $footerPost->setName('FR footer');
        $footerPost->setLocale('fr');
        $footerPost->setContent('<p>FR</p>');
        $footerPost->setActive(true);
        $footerPost->setPosition(1);
        $footer->addPost($footerPost);
        $em->persist($footerPost);
        $em->flush();

        $result = $copy->copyLocale('en');
        $this->assertArrayHasKey('success', $result, $result['warning'] ?? json_encode($result));

        $enMenu = $menuRepo->findOneByLocaleAndReferenceName('en', 'posts-menu');
        $this->assertInstanceOf(Menu::class, $enMenu);
        $this->assertSame('en', $enMenu->getLocale());

        $em->clear();
        $enFooter = $em->getRepository(Section::class)->findOneBy([
            'locale' => 'en',
            'referenceName' => 'footer-main',
        ]);
        $this->assertInstanceOf(Section::class, $enFooter);
        $enPosts = $em->getRepository(Post::class)->findBy(['section' => $enFooter]);
        $this->assertNotEmpty($enPosts);
        $this->assertSame('en', $enPosts[0]->getLocale());
    }

    public function testCopyLocalePreservesMenuHierarchyAndOrder(): void
    {
        $em = $this->em;
        /** @var LocaleCopyService $copy */
        $copy = static::getContainer()->get(LocaleCopyService::class);
        /** @var MenuRepository $menuRepo */
        $menuRepo = static::getContainer()->get(MenuRepository::class);

        $solution = new Menu();
        $solution->setName('Solution');
        $solution->setLocale('fr');
        $solution->setReferenceName('solution');
        $solution->setPosition(1);
        $em->persist($solution);

        $niveau2 = new Menu();
        $niveau2->setName('Niveau2');
        $niveau2->setLocale('fr');
        $niveau2->setReferenceName('niveau2');
        $niveau2->setPosition(1);
        $niveau2->setParent($solution);
        $em->persist($niveau2);

        $niveau3 = new Menu();
        $niveau3->setName('Niveau3');
        $niveau3->setLocale('fr');
        $niveau3->setReferenceName('niveau3');
        $niveau3->setPosition(1);
        $niveau3->setParent($niveau2);
        $em->persist($niveau3);
        $em->flush();

        $result = $copy->copyLocale('en', 'fr');
        $this->assertArrayHasKey('success', $result, $result['warning'] ?? json_encode($result));

        $em->clear();

        $enSolution = $menuRepo->findOneByLocaleAndReferenceName('en', 'solution');
        $enNiveau2 = $menuRepo->findOneByLocaleAndReferenceName('en', 'niveau2');
        $enNiveau3 = $menuRepo->findOneByLocaleAndReferenceName('en', 'niveau3');

        $this->assertInstanceOf(Menu::class, $enSolution);
        $this->assertInstanceOf(Menu::class, $enNiveau2);
        $this->assertInstanceOf(Menu::class, $enNiveau3);
        $this->assertNull($enSolution->getParent());
        $this->assertSame($enSolution, $enNiveau2->getParent());
        $this->assertSame($enNiveau2, $enNiveau3->getParent());
        $this->assertSame(1, $enSolution->getPosition());
        $this->assertSame(1, $enNiveau2->getPosition());
        $this->assertSame(1, $enNiveau3->getPosition());
    }

    public function testCopyLocaleAlsoCopiesFooterPostsIntoExistingTargetFooterSection(): void
    {
        $em = $this->em;
        /** @var LocaleCopyService $copy */
        $copy = static::getContainer()->get(LocaleCopyService::class);

        $footerTpl = $em->getRepository(Template::class)->findOneBy(['code' => FooterSectionService::TEMPLATE_CODE]);
        $this->assertInstanceOf(Template::class, $footerTpl);

        $sourceFooter = new Section();
        $sourceFooter->setMenu(null);
        $sourceFooter->setLocale('fr');
        $sourceFooter->setReferenceName('footer-shared');
        $sourceFooter->setTemplate($footerTpl);
        $sourceFooter->setPosition(1);
        $sourceFooter->setActive(true);
        $em->persist($sourceFooter);

        $sourcePost = new Post();
        $sourcePost->setName('Footer FR');
        $sourcePost->setLocale('fr');
        $sourcePost->setContent('<p>FR Footer</p>');
        $sourcePost->setActive(true);
        $sourcePost->setPosition(1);
        $sourceFooter->addPost($sourcePost);
        $em->persist($sourcePost);

        $targetFooter = new Section();
        $targetFooter->setMenu(null);
        $targetFooter->setLocale('en');
        $targetFooter->setReferenceName('footer-shared');
        $targetFooter->setTemplate($footerTpl);
        $targetFooter->setPosition(1);
        $targetFooter->setActive(true);
        $em->persist($targetFooter);
        $em->flush();

        $result = $copy->copyLocale('en', 'fr');
        $this->assertArrayHasKey('success', $result, $result['warning'] ?? json_encode($result));

        $em->clear();
        $targetFooter = $em->getRepository(Section::class)->findOneBy([
            'locale' => 'en',
            'referenceName' => 'footer-shared',
        ]);
        $this->assertInstanceOf(Section::class, $targetFooter);
        $posts = $em->getRepository(Post::class)->findBy(['section' => $targetFooter], ['position' => 'ASC']);
        $this->assertCount(1, $posts);
        $this->assertSame('en', $posts[0]->getLocale());
        $this->assertSame('Footer FR', $posts[0]->getName());
    }
}
