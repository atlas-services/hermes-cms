<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Menu;
use App\Service\MenuReferenceNameResolver;
use PHPUnit\Framework\TestCase;

final class MenuReferenceNameResolverTest extends TestCase
{
    public function testResolveFromNameWhenReferenceIsRef(): void
    {
        $resolver = new MenuReferenceNameResolver();

        $menu = new Menu();
        $menu->setReferenceName('ref');
        $menu->setName('Posts Menu');

        self::assertSame('posts-menu', $resolver->resolve($menu));
    }

    public function testResolveKeepsExplicitReferenceName(): void
    {
        $resolver = new MenuReferenceNameResolver();

        $menu = new Menu();
        $menu->setReferenceName('my-page');
        $menu->setName('Other');

        self::assertSame('my-page', $resolver->resolve($menu));
    }
}
