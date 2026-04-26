<?php
// tests/Entity/MenuTest.php

namespace App\Tests\Entity;

use App\Entity\Menu;
use PHPUnit\Framework\TestCase;

class MenuTest extends TestCase
{
    public function testDepth(): void
    {
        $root = new Menu();
        $child = new Menu();
        $subChild = new Menu();

        $child->setParent($root);
        $subChild->setParent($child);

        $this->assertEquals(0, $root->getDepth());
        $this->assertEquals(1, $child->getDepth());
        $this->assertEquals(2, $subChild->getDepth());
    }

    public function testIsRoot(): void
    {
        $menu = new Menu();

        $this->assertTrue($menu->isRoot());
    }

    public function testIsLeaf(): void
    {
        $menu = new Menu();

        $this->assertTrue($menu->isLeaf());

        $child = new Menu();
        $menu->addChild($child);

        $this->assertFalse($menu->isLeaf());
    }

    public function testCannotBeOwnParent(): void
    {
        $this->expectException(\Exception::class);

        $menu = new Menu();
        $menu->setParent($menu);
    }

    public function testImmediateParentOrSelf(): void
    {
        $root = new Menu();
        $child = new Menu();

        $child->setParent($root);

        $this->assertSame($root, $child->getParentOrSelf());
        $this->assertSame($root, $root->getParentOrSelf());
    }
}
