<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DataFixtures\PostFixtures;
use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use App\Service\FrontLocaleSwitcherService;
use App\Tests\Base\BaseKernelTestCase;

final class FrontLocaleSwitcherServiceTest extends BaseKernelTestCase
{
    protected function loadFixtures(): array
    {
        return [new PostFixtures()];
    }

    public function testBuildLinksUsesReferenceNameForEquivalentPage(): void
    {
        $em = $this->em;
        /** @var FrontLocaleSwitcherService $switcher */
        $switcher = static::getContainer()->get(FrontLocaleSwitcherService::class);

        $frMenu = $em->getRepository(Menu::class)->findOneBy(['name' => 'Posts Menu']);
        $this->assertInstanceOf(Menu::class, $frMenu);
        $frMenu->setReferenceName('ref');
        $frMenu->setLocale('fr');
        foreach ($frMenu->getSections() as $section) {
            $section->setActive(true);
            foreach ($section->getPosts() as $post) {
                $post->setActive(true);
                $post->setLocale('fr');
                if ($post->getContent() === null || $post->getContent() === '') {
                    $post->setContent('<p>FR</p>');
                }
            }
        }
        $this->assertNotEmpty($frMenu->getSections(), 'fixture menu should have sections');

        $enMenu = new Menu();
        $enMenu->setName('Posts Menu EN');
        $enMenu->setLocale('en');
        $enMenu->setReferenceName('posts-menu');
        $enMenu->setPosition(2);
        $em->persist($enMenu);

        $template = $em->getRepository(Template::class)->findOneBy(['code' => 'libre']);
        $this->assertInstanceOf(Template::class, $template);

        $enSection = new Section();
        $enSection->setMenu($enMenu);
        $enSection->setTemplate($template);
        $enSection->setPosition(1);
        $enSection->setTemplateWidth(10);
        $em->persist($enSection);

        $enPost = new Post();
        $enPost->setName('EN post');
        $enPost->setLocale('en');
        $enPost->setContent('<p>EN</p>');
        $enPost->setActive(true);
        $enPost->setPosition(1);
        $enSection->addPost($enPost);
        $em->persist($enPost);
        $em->flush();
        $em->clear();

        $frMenu = $em->find(Menu::class, $frMenu->getId());
        $enMenu = $em->find(Menu::class, $enMenu->getId());
        $this->assertInstanceOf(Menu::class, $frMenu);
        $this->assertInstanceOf(Menu::class, $enMenu);

        $frontMenu = static::getContainer()->get(\App\Service\FrontMenuService::class);
        $this->assertNotEmpty($frontMenu->getVisibleFrontSections($frMenu, 'fr'));
        $this->assertNotEmpty($frontMenu->getVisibleFrontSections($enMenu, 'en'));

        $links = $switcher->buildLinks($frMenu, 'fr');
        $this->assertGreaterThanOrEqual(2, \count($links));

        $enLink = null;
        foreach ($links as $link) {
            if ($link['locale'] === 'en') {
                $enLink = $link;
                break;
            }
        }
        $this->assertNotNull($enLink);
        $this->assertStringContainsString('/en/', $enLink['url']);
        $this->assertMatchesRegularExpression('#/en/[^/]*posts-menu#', $enLink['url']);

        $frActive = array_filter($links, static fn (array $l): bool => $l['locale'] === 'fr' && $l['active']);
        $this->assertCount(1, $frActive);
    }

}
