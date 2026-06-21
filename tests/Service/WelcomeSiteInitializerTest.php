<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Menu;
use App\Repository\ConfigRepository;
use App\Repository\MenuRepository;
use App\Repository\SectionRepository;
use App\Service\FooterSectionService;
use App\Service\WelcomeSiteInitializer;
use App\Tests\Base\BaseKernelTestCase;

final class WelcomeSiteInitializerTest extends BaseKernelTestCase
{
    public function testSkipsWhenMenusExist(): void
    {
        /** @var WelcomeSiteInitializer $initializer */
        $initializer = static::getContainer()->get(WelcomeSiteInitializer::class);

        $result = $initializer->initializeIfEmpty('fr');

        self::assertFalse($result['created']);
        self::assertSame('menus_exist', $result['skipped_reason']);
    }

    public function testCreatesAccueilWhenDatabaseHasNoMenus(): void
    {
        foreach ($this->em->getRepository(Menu::class)->findAll() as $menu) {
            $this->em->remove($menu);
        }
        $this->em->flush();

        /** @var WelcomeSiteInitializer $initializer */
        $initializer = static::getContainer()->get(WelcomeSiteInitializer::class);

        $result = $initializer->initializeIfEmpty('fr');

        self::assertTrue($result['created']);
        self::assertNull($result['skipped_reason']);

        /** @var MenuRepository $menuRepository */
        $menuRepository = static::getContainer()->get(MenuRepository::class);
        $appName = (string) static::getContainer()->getParameter('app.name');
        $menu = $menuRepository->findOneBySlugPath('fr', [WelcomeSiteInitializer::MENU_SLUG], false);

        self::assertInstanceOf(Menu::class, $menu);
        self::assertSame(mb_strtoupper($appName, 'UTF-8'), $menu->getName());
        self::assertTrue($menu->isActive());
        self::assertCount(1, $menu->getSections());

        $post = $menu->getSections()->first()->getPosts()->first();
        $content = (string) $post->getContent();
        self::assertStringContainsString('est en construction', $content);
        self::assertStringContainsString('SITE VITRINE', $content);
        self::assertStringContainsString('Hermes CMS', $content);
        self::assertStringContainsString('site vitrine', $content);
        self::assertStringNotContainsString(WelcomeSiteInitializer::PLACEHOLDER, $content);

        $contact = $menuRepository->findOneBySlugPath('fr', [WelcomeSiteInitializer::CONTACT_MENU_SLUG], false);
        self::assertInstanceOf(Menu::class, $contact);
        self::assertSame(WelcomeSiteInitializer::CONTACT_MENU_NAME, $contact->getName());
        self::assertTrue($contact->isActive());
        self::assertCount(1, $contact->getSections());
        self::assertSame('contact', $contact->getSections()->first()->getTemplate()?->getCode());

        $expectedLegalNames = [
            'mentions-legales' => 'MENTIONS LÉGALES',
            'confidentialite' => 'CONFIDENTIALITÉ',
            'cgu-cgv' => 'CGU-CGV',
        ];

        foreach ($expectedLegalNames as $slug => $expectedName) {
            $legalMenu = $menuRepository->findOneBySlugPath('fr', [$slug], false);
            self::assertInstanceOf(Menu::class, $legalMenu, $slug);
            self::assertSame($expectedName, $legalMenu->getName());
            self::assertFalse($legalMenu->isActive());
            self::assertCount(1, $legalMenu->getSections());
            $legalPost = $legalMenu->getSections()->first()->getPosts()->first();
            $legalContent = (string) $legalPost->getContent();
            self::assertStringContainsString($appName, $legalContent);
            self::assertStringContainsString('accordion', $legalContent);
            self::assertStringContainsString('06 11 22 33 44', $legalContent);
            self::assertStringContainsString('12 rue des Lilas', $legalContent);
            self::assertStringNotContainsString(WelcomeSiteInitializer::PLACEHOLDER, $legalContent);
        }

        /** @var ConfigRepository $configRepository */
        $configRepository = static::getContainer()->get(ConfigRepository::class);
        self::assertSame('#000000', $configRepository->findOneBy(['code' => 'bgcolor'])?->getValue());
        self::assertSame('#ffffff', $configRepository->findOneBy(['code' => 'text_color'])?->getValue());
        self::assertSame('#ff00ff', $configRepository->findOneBy(['code' => 'nav_color_active'])?->getValue());
        self::assertSame('#000000', $configRepository->findOneBy(['code' => 'footer_bgcolor'])?->getValue());

        /** @var SectionRepository $sectionRepository */
        $sectionRepository = static::getContainer()->get(SectionRepository::class);
        $footer = $sectionRepository->findOneFooterByLocaleAndReference('fr', WelcomeSiteInitializer::FOOTER_REFERENCE);
        self::assertNotNull($footer);
        self::assertSame(FooterSectionService::TEMPLATE_CODE, $footer->getTemplate()?->getCode());
        self::assertSame('#000000', $footer->getTemplateBgcolor());
        self::assertStringContainsString('/fr/mentions-legales', (string) $footer->getPosts()->first()->getContent());
    }
}
