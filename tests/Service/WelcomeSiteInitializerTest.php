<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Menu;
use App\Repository\MenuRepository;
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
        $menu = $menuRepository->findOneBySlugPath('fr', [WelcomeSiteInitializer::MENU_SLUG], false);

        self::assertInstanceOf(Menu::class, $menu);
        self::assertSame(WelcomeSiteInitializer::MENU_NAME, $menu->getName());
        self::assertTrue($menu->isActive());
        self::assertCount(1, $menu->getSections());

        $post = $menu->getSections()->first()->getPosts()->first();
        $content = (string) $post->getContent();
        self::assertStringContainsString('est en construction', $content);
        self::assertStringContainsString('SITE VITRINE', $content);
        self::assertStringContainsString('Hermes CMS', $content);
        self::assertStringContainsString('site vitrine', $content);
        self::assertStringNotContainsString(WelcomeSiteInitializer::PLACEHOLDER, $content);
    }
}
