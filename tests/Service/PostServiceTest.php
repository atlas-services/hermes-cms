<?php

namespace App\Tests\Service;

use App\DataFixtures\PostFixtures;
use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use App\Service\PostService;
use App\Tests\Base\BaseKernelTestCase;

class PostServiceTest extends BaseKernelTestCase
{
    private PostService $postService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->postService = static::getContainer()->get(PostService::class);
    }

    protected function loadFixtures(): array
    {
        return [
            new PostFixtures(),
        ];
    }

    public function testCreatePostWithSection(): void
    {
        $section = $this->em->getRepository(Section::class)->findOneBy(['position' => 1]);
        $this->assertNotNull($section);

        $post = new Post();
        $post->setName('Service Post');

        $created = $this->postService->create($post, $section);

        $this->assertSame($section, $created->getSection());
        $this->assertEquals(3, $created->getPosition());
    }

    public function testCreatePostFromMenuCreatesSection(): void
    {
        $menu = $this->em->getRepository(Menu::class)->findOneBy(['name' => 'Leaf Menu']);
        $this->assertNotNull($menu);

        $post = new Post();
        $post->setName('Service Post Menu');

        $created = $this->postService->createFromMenu($post, $menu, null);

        $this->assertSame($menu, $created->getSection()->getMenu());
        $this->assertNotNull($created->getSection()->getTemplate());
    }

    public function testUpdatePostChangesTemplate(): void
    {
        $post = $this->em->getRepository(Post::class)->findOneBy(['name' => 'Post 1']);
        $this->assertNotNull($post);

        $template = new Template();
        $template->setName('Updated Template');
        $template->setCode('updated_template');
        $template->setType('liste');
        $template->setSummary('Updated template');
        $this->em->persist($template);
        $this->em->flush();

        $updated = $this->postService->update($post, null, $template);

        $this->assertSame($template, $updated->getSection()->getTemplate());
    }
}
