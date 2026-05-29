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
}
