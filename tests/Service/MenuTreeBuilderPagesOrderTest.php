<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DataFixtures\MenuFixtures;
use App\Entity\Menu;
use App\Entity\Section;
use App\Entity\Template;
use App\Repository\MenuRepository;
use App\Service\MenuTreeBuilder;
use App\Tests\Base\BaseKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class MenuTreeBuilderPagesOrderTest extends BaseKernelTestCase
{
    protected function loadFixtures(): array
    {
        return [new MenuFixtures()];
    }

    public function testOrderPagesByTreeFollowsMenuHierarchy(): void
    {
        $em = $this->em;
        $root1 = $em->getRepository(Menu::class)->findOneBy(['name' => 'Root 1']);
        $root2 = $em->getRepository(Menu::class)->findOneBy(['name' => 'Root 2']);
        $child1 = $em->getRepository(Menu::class)->findOneBy(['name' => 'Child 1']);
        self::assertNotNull($root1);
        self::assertNotNull($root2);
        self::assertNotNull($child1);

        $template = new Template();
        $template->setCode('libre');
        $template->setName('Libre');
        $template->setType('libre');
        $template->setSummary('Libre');
        $em->persist($template);

        $this->attachPage($child1, $template, $em);
        $this->attachPage($root2, $template, $em);
        $em->flush();

        $repo = static::getContainer()->get(MenuRepository::class);
        $builder = static::getContainer()->get(MenuTreeBuilder::class);

        $ordered = $builder->orderPagesByTree($repo->findPages());

        self::assertCount(2, $ordered);
        self::assertSame('Child 1', $ordered[0]['menu']->getName());
        self::assertSame(1, $ordered[0]['depth']);
        self::assertSame('Root 1 / Child 1', $ordered[0]['label']);
        self::assertSame('Root 2', $ordered[1]['menu']->getName());
        self::assertSame(0, $ordered[1]['depth']);
        self::assertSame('Root 2', $ordered[1]['label']);
    }

    private function attachPage(Menu $menu, Template $template, EntityManagerInterface $em): void
    {
        $section = new Section();
        $section->setMenu($menu);
        $section->setTemplate($template);
        $section->setPosition(1);
        $menu->addSection($section);
        $em->persist($section);
    }
}
