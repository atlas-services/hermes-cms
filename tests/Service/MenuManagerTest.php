<?php

namespace App\Tests\Service;

use App\DataFixtures\MenuFixtures;
use App\Entity\Menu;
use App\Exception\MaxDepthExceededException;
use App\Service\MenuManager;
use App\Tests\Base\BaseKernelTestCase;

class MenuManagerTest extends BaseKernelTestCase
{
    private MenuManager $menuManager;

    protected function setUp(): void
    {
        $this->initDatabase();

        $this->menuManager = static::getContainer()->get(MenuManager::class);
    }

    protected function loadFixtures(): array
    {
        return [
            new MenuFixtures(),
        ];
    }

    // -------------------------
    // STRUCTURE
    // -------------------------

    public function testCanAddChildWithinDepth(): void
    {
        $root = new Menu();
        $root->setName('Root');

        $root->setParent(null);

        $this->assertTrue($this->menuManager->canAddChild($root));
    }

    public function testCannotAddChildWhenMaxDepthExceeded(): void
    {
        $root = new Menu();
        $root->setName('Root');

        $root->setParent(null);

        $current = $root;

        for ($i = 0; $i < 10; $i++) {
            $child = new Menu();
            $child->setName('Child ' . $i);
            $child->setParent($current);

            $current = $child;
        }

        $this->expectException(MaxDepthExceededException::class);

        $this->menuManager->assertCanAddChild($current->getParent());
    }

    // -------------------------
    // CREATE
    // -------------------------

    public function testCreateRootMenu(): void
    {
        $menu = new Menu();
        $menu->setName('New Root Menu');
        $menu->setParent(null);

        $this->menuManager->create($menu);

        $this->assertNotNull($menu->getPosition());
        $this->assertNull($menu->getParent());
    }

    public function testCreateSubMenu(): void
    {
        $repo = static::getContainer()->get('doctrine')->getRepository(Menu::class);

        $parent = $repo->findOneBy(['name' => 'Root 1']);

        $this->assertNotNull($parent);

        $menu = new Menu();
        $menu->setName('Sub Menu');
        $menu->setParent($parent);

        $this->menuManager->create($menu);

        $this->assertNotNull($menu->getPosition());
        $this->assertSame($parent, $menu->getParent());
    }

    // -------------------------
    // READ
    // -------------------------

    public function testGetTreeReturnsRoots(): void
    {
        $tree = $this->menuManager->getTree();

        $this->assertIsArray($tree);
        $this->assertNotEmpty($tree);

        foreach ($tree as $menu) {
            $this->assertInstanceOf(Menu::class, $menu);
            $this->assertNull($menu->getParent());
        }
    }
}
