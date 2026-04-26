<?php

namespace App\Tests\Repository;

use App\Entity\Menu;
use App\Tests\Base\BaseKernelTestCase;

class MenuRepositoryTest extends BaseKernelTestCase
{
    public function testGetMenusReturnsOnlyRootOrdered(): void
    {
        $repo = $this->em->getRepository(Menu::class);

        $roots = $repo->findRoots();

        $this->assertIsArray($roots);
    }

    public function testGetNextPositionForRoot(): void
    {
        $repo = $this->em->getRepository(Menu::class);

        $pos = $repo->getNextPosition(null);

        $this->assertIsInt($pos);
    }

    public function testGetNextPositionForChildren(): void
    {
        $repo = $this->em->getRepository(Menu::class);

        $parent = new Menu();
        $parent->setName('Root');

        $this->em->persist($parent);
        $this->em->flush();

        $pos = $repo->getNextPosition($parent);

        $this->assertIsInt($pos);
    }
}
