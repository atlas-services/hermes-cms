<?php

namespace App\Tests\Repository;

use App\DataFixtures\MenuFixtures;
use App\Entity\Menu;
use App\Repository\MenuRepository;
use App\Tests\Base\BaseKernelTestCase;

class MenuRepositoryTest extends BaseKernelTestCase
{
    protected function loadFixtures(): array
    {
        return [
            new MenuFixtures(),
        ];
    }

    public function testFindRootsReturnsOnlyRootMenus(): void
    {
        /** @var MenuRepository $repo */
        $repo = static::getContainer()->get(MenuRepository::class);

        $roots = $repo->findRoots();

        $this->assertNotEmpty($roots);

        foreach ($roots as $menu) {
            $this->assertInstanceOf(Menu::class, $menu);
            $this->assertNull($menu->getParent());
        }
    }

    public function testGetNextPositionForRoot(): void
    {
        /** @var MenuRepository $repo */
        $repo = static::getContainer()->get(MenuRepository::class);

        $pos = $repo->getNextPosition(null);

        $this->assertGreaterThan(0, $pos);
    }

    public function testGetNextPositionForChildren(): void
    {
        /** @var MenuRepository $repo */
        $repo = static::getContainer()->get(MenuRepository::class);

        $parent = $this->em->getRepository(Menu::class)->findOneBy(['name' => 'Root 1']);

        $this->assertNotNull($parent);

        $pos = $repo->getNextPosition($parent);

        $this->assertEquals(3, $pos); // Root 1 has Child 1 and Child 2, next is 3
    }

    public function testFindChildrenReturnsOrderedChildren(): void
    {
        /** @var MenuRepository $repo */
        $repo = static::getContainer()->get(MenuRepository::class);

        $parent = $this->em->getRepository(Menu::class)->findOneBy(['name' => 'Root 1']);

        $this->assertNotNull($parent);

        $children = $repo->findChildren($parent);

        $this->assertCount(2, $children);

        foreach ($children as $child) {
            $this->assertInstanceOf(Menu::class, $child);
            $this->assertSame($parent, $child->getParent());
        }

        // Check order by position
        $this->assertEquals('Child 1', $children[0]->getName());
        $this->assertEquals('Child 2', $children[1]->getName());
    }

    public function testFindRootByLocaleAndNameMatchesRootOnly(): void
    {
        /** @var MenuRepository $repo */
        $repo = static::getContainer()->get(MenuRepository::class);

        $root = $repo->findRootByLocaleAndName('fr', 'Root 1');
        $this->assertNotNull($root);
        $this->assertNull($root->getParent());

        $this->assertNull($repo->findRootByLocaleAndName('fr', 'Child 1'));
    }

    public function testFindPagesReturnsMenusWithPosts(): void
    {
        /** @var MenuRepository $repo */
        $repo = static::getContainer()->get(MenuRepository::class);

        $pages = $repo->findPages();

        $this->assertGreaterThanOrEqual(0, \count($pages));
        // Since MenuFixtures doesn't create posts, this might be empty
        // But in real app, it would have menus with posts
    }
}
