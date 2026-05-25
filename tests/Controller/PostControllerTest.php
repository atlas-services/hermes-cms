<?php

namespace App\Tests\Controller;

use App\DataFixtures\PostFixtures;
use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use App\Tests\Base\AbstractControllerWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class PostControllerTest extends AbstractControllerWebTestCase
{
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

        $libre = $this->em->getRepository(Template::class)->findOneBy(['code' => 'libre']);
        $this->assertNotNull($libre);

        $this->client->submitForm('post[save]', [
            'post[name]' => 'New Post Test',
            'post[template]' => (string) $libre->getId(),
            'post[content]' => '<p>Contenu requis pour le gabarit libre.</p>',
        ]);

        $this->assertResponseRedirects();
        $this->assertMatchesRegularExpression('#/fr/admin/post/\\d+/edit#', (string) $this->client->getResponse()->headers->get('Location'));
    }

    // -------------------------
    // CREATE VIA SECTION
    // -------------------------

    public function testCreatePostViaSection(): void
    {
        $this->login();

        $postsMenu = $this->em->getRepository(Menu::class)->findOneBy(['name' => 'Posts Menu']);
        $this->assertNotNull($postsMenu);

        $section = $this->em
            ->getRepository(Section::class)
            ->findOneBy(['menu' => $postsMenu, 'position' => 1]);

        $this->assertNotNull($section);

        $this->client->request('GET', '/fr/admin/post/section/' . $section->getId() . '/new');

        $this->client->submitForm('post[save]', [
            'post[name]' => 'New Post in Section Test',
            'post[content]' => '<p>Contenu pour section libre.</p>',
        ]);

        $this->assertResponseRedirects();
        $this->assertMatchesRegularExpression('#/fr/admin/post/\\d+/edit#', (string) $this->client->getResponse()->headers->get('Location'));
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

        $this->em->clear();

        $this->client->request('GET', '/fr/admin/post/' . $postId . '/edit');

        $this->client->submitForm('post[save]', [
            'post[name]' => 'Updated Post',
            'post[content]' => '<p>Contenu pour mise à jour.</p>',
        ]);

        $this->assertResponseRedirects();
        $this->assertMatchesRegularExpression('#/fr/admin/post/' . $postId . '/edit#', (string) $this->client->getResponse()->headers->get('Location'));
    }
}