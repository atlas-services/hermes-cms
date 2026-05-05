<?php

namespace App\Tests\Repository;

use App\DataFixtures\PostFixtures;
use App\Entity\Menu;
use App\Entity\Post;
use App\Repository\PostRepository;
use App\Tests\Base\BaseKernelTestCase;

class PostRepositoryTest extends BaseKernelTestCase
{
    protected function loadFixtures(): array
    {
        return [
            new PostFixtures(),
        ];
    }

    public function testGetMaxPositionForMenu(): void
    {
        /** @var PostRepository $repository */
        $repository = static::getContainer()->get(PostRepository::class);
        $menu = $this->em->getRepository(Menu::class)->findOneBy(['name' => 'Posts Menu']);

        $this->assertNotNull($menu);
        $this->assertEquals(2, $repository->getMaxPosition($menu));
    }

    public function testGetLastPostPosition(): void
    {
        /** @var PostRepository $repository */
        $repository = static::getContainer()->get(PostRepository::class);

        $this->assertEquals(2, $repository->getLastPostPosition());
    }

    public function testSwitchActiveTogglesPost(): void
    {
        /** @var PostRepository $repository */
        $repository = static::getContainer()->get(PostRepository::class);
        $post = $repository->findOneBy(['name' => 'Post 1']);

        $this->assertNotNull($post);

        $initial = $post->isActive();
        $toggled = $repository->switchActive($post->getId());

        $this->assertEquals(!$initial, $toggled->isActive());
    }
}
