<?php

namespace App\Tests\Controller;

use App\DataFixtures\PostFixtures;
use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Tests\Base\BaseControllerTest;
use Doctrine\ORM\EntityManagerInterface;

class PostControllerTest extends BaseControllerTest
{
    const CREATE = 'Créer';
    const SAVE = 'Mettre à jour';

    protected function tearDown(): void
    {
        $this->em->clear();
        parent::tearDown();
    }

    protected function loadFixtures(): void
    {
        parent::loadFixtures();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        (new PostFixtures())->load($em);
    }

    // -------------------------
    // INDEX
    // -------------------------

    public function testIndexRequiresLogin(): void
    {
        $this->client->request('GET', '/fr/admin/post');

        $this->assertResponseRedirects();
    }

    public function testIndexAfterLogin(): void
    {
        $this->login();

        $this->client->request('GET', '/fr/admin/post');

        $this->assertResponseIsSuccessful();
    }

    // -------------------------
    // CREATE VIA MENU
    // -------------------------

    public function testCreatePostViaMenu(): void
    {
        $this->login();

        $menu = $this->em
            ->getRepository(Menu::class)
            ->findOneBy(['name' => 'Leaf Menu']);

        $this->assertNotNull($menu);

        $this->client->request('GET', '/fr/admin/post/menu/' . $menu->getId() . '/new');

        $this->client->submitForm(self::CREATE, [
            'post[name]' => 'New Post Test',
        ]);

        $this->assertResponseRedirects('/fr/admin/menu/' . $menu->getId() . '/edit');
    }

    // -------------------------
    // CREATE VIA SECTION
    // -------------------------

    public function testCreatePostViaSection(): void
    {
        $this->login();

        $section = $this->em
            ->getRepository(Section::class)
            ->findOneBy(['position' => 1]);

        $this->assertNotNull($section);

        $this->client->request('GET', '/fr/admin/post/section/' . $section->getId() . '/new');

        $this->client->submitForm(self::CREATE, [
            'post[name]' => 'New Post in Section Test',
        ]);

        $this->assertResponseRedirects('/fr/admin/post?page=' . $section->getMenu()->getId());
    }

    // -------------------------
    // EDIT
    // -------------------------

    public function testEditPost(): void
    {
        $this->login();

        $post = $this->em
            ->getRepository(Post::class)
            ->findOneBy(['name' => 'Post 1']);

        $this->assertNotNull($post);

        $postId = $post->getId();
        $menuId = $post->getSection()->getMenu()->getId();

        $this->em->clear();

        $this->client->request('GET', '/fr/admin/post/' . $postId . '/edit');

        $this->client->submitForm(self::SAVE, [
            'post[name]' => 'Updated Post',
        ]);

        $this->assertResponseRedirects('/fr/admin/menu/' . $menuId . '/edit');
    }
}