<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\Menu;
use App\Repository\MenuRepository;
use App\Service\LegalPagesInitializer;
use App\Tests\Base\BaseKernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class InitMentionsLegalesCommandTest extends BaseKernelTestCase
{
    public function testCommandCreatesThreeInactiveLegalMenus(): void
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:init-mentions-legales'));
        $tester->execute(['--locale' => 'fr']);

        self::assertSame(0, $tester->getStatusCode());

        /** @var MenuRepository $menuRepository */
        $menuRepository = static::getContainer()->get(MenuRepository::class);

        foreach (LegalPagesInitializer::PAGES as $page) {
            $menu = $menuRepository->findOneBySlugPath('fr', [$page['slug']], false);
            self::assertInstanceOf(Menu::class, $menu, $page['slug']);
            self::assertFalse($menu->isActive());
            self::assertCount(1, $menu->getSections());
            self::assertCount(1, $menu->getSections()->first()->getPosts());
        }
    }

    public function testCommandIsIdempotent(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:init-mentions-legales');
        $tester = new CommandTester($command);
        $tester->execute([]);
        $tester->execute([]);

        self::assertStringContainsString('0 page(s) créée(s), 3 déjà présente(s)', $tester->getDisplay());
    }
}
