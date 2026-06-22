<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DataFixtures\PostFixtures;
use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use App\Service\FooterSectionService;
use App\Service\FrontMenuService;
use App\Service\TopbarSectionService;
use App\Tests\Base\BaseKernelTestCase;

final class FrontMenuFooterSectionsTest extends BaseKernelTestCase
{
    protected function loadFixtures(): array
    {
        return [new PostFixtures()];
    }

    public function testGlobalSectionsAreExcludedFromPageAndLoadedGlobally(): void
    {
        $em = $this->em;
        /** @var FrontMenuService $frontMenu */
        $frontMenu = static::getContainer()->get(FrontMenuService::class);

        $footerTpl = $em->getRepository(Template::class)->findOneBy(['code' => FooterSectionService::TEMPLATE_CODE]);
        $this->assertInstanceOf(Template::class, $footerTpl);
        $topbarTpl = $em->getRepository(Template::class)->findOneBy(['code' => TopbarSectionService::TEMPLATE_CODE]);
        $this->assertInstanceOf(Template::class, $topbarTpl);

        $menu = $em->getRepository(Menu::class)->findOneBy(['name' => 'Posts Menu']);
        $this->assertInstanceOf(Menu::class, $menu);

        $footerSection = new Section();
        $footerSection->setMenu(null);
        $footerSection->setLocale('fr');
        $footerSection->setReferenceName('footer-test');
        $footerSection->setTemplate($footerTpl);
        $footerSection->setPosition(99);
        $footerSection->setActive(true);
        $em->persist($footerSection);

        $footerPost = new Post();
        $footerPost->setName('Footer post');
        $footerPost->setLocale('fr');
        $footerPost->setActive(true);
        $footerPost->setContent('<p>Footer</p>');
        $footerPost->setPosition(1);
        $footerSection->addPost($footerPost);
        $em->persist($footerPost);

        $topbarSection = new Section();
        $topbarSection->setMenu(null);
        $topbarSection->setLocale('fr');
        $topbarSection->setReferenceName('topbar-test');
        $topbarSection->setTemplate($topbarTpl);
        $topbarSection->setPosition(1);
        $topbarSection->setActive(true);
        $em->persist($topbarSection);

        $topbarPost = new Post();
        $topbarPost->setName('Topbar post');
        $topbarPost->setLocale('fr');
        $topbarPost->setActive(true);
        $topbarPost->setContent('<p>Topbar</p>');
        $topbarPost->setPosition(1);
        $topbarSection->addPost($topbarPost);
        $em->persist($topbarPost);

        $em->flush();

        $bodyBlocks = $frontMenu->getVisibleFrontSections($menu);
        foreach ($bodyBlocks as $block) {
            $this->assertFalse($block['section']->isGlobalSection());
        }

        $footerBlocks = $frontMenu->getVisibleFooterSections('fr');
        $this->assertNotEmpty($footerBlocks);
        $this->assertTrue($footerBlocks[0]['section']->isFooterSection());

        $this->assertSame([], $frontMenu->getVisibleFooterSections('en'));

        $topbarBlocks = $frontMenu->getVisibleTopbarSections('fr');
        $this->assertNotEmpty($topbarBlocks);
        $this->assertTrue($topbarBlocks[0]['section']->isTopbarSection());

        $this->assertSame([], $frontMenu->getVisibleTopbarSections('en'));
    }
}
