<?php

namespace App\Tests\Controller;

use App\DataFixtures\MenuFixtures;
use App\Entity\Menu;
use App\Tests\Base\AbstractControllerWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class MenuControllerTest extends AbstractControllerWebTestCase
{
    const CREATE = 'Créer';
    const SAVE = 'Mettre à jour';

    protected function loadFixtures(): void
    {
        parent::loadFixtures();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        (new MenuFixtures())->load($em);
    }

    // -------------------------
    // INDEX
    // -------------------------

    public function testIndexRequiresLogin(): void
    {
        $this->client->request('GET', '/fr/admin/menu');

        $this->assertResponseRedirects();
    }

    public function testIndexAfterLogin(): void
    {
        $this->login();

        $this->client->request('GET', '/fr/admin/menu');

        $this->assertResponseIsSuccessful();
    }

    // -------------------------
    // CREATE ROOT
    // -------------------------

    public function testCreateRootMenu(): void
    {
        $this->login();

        $this->client->request('GET', '/fr/admin/menu/new');

        $this->client->submitForm(self::CREATE, [
            'menu[name]' => 'Root Menu Test',
        ]);

        $this->assertResponseRedirects('/fr/admin/menu');
    }

    // -------------------------
    // CREATE SUBMENU
    // -------------------------

    public function testCreateSubMenu(): void
    {
        $this->login();

        $parent = $this->em
            ->getRepository(Menu::class)
            ->findOneBy(['name' => 'Root 1']);

        $this->assertNotNull($parent);

        $this->client->request('GET', '/fr/admin/menu/new');

        $this->client->submitForm(self::CREATE, [
            'menu[name]' => 'Sub Menu Test',
            'menu[parent]' => $parent->getId(),
        ]);

        $this->assertResponseRedirects('/fr/admin/menu');
    }

    // -------------------------
    // SHOW
    // -------------------------

    public function testShowMenu(): void
    {
        $this->login();

        $menu = $this->em
            ->getRepository(Menu::class)
            ->findOneBy(['parent' => null]);

        $this->assertNotNull($menu);

        $this->client->request('GET', '/fr/admin/menu/' . $menu->getId());


        $this->assertResponseIsSuccessful();
    }

    // -------------------------
    // EDIT
    // -------------------------

    public function testEditMenu(): void
    {
        $this->login();

        $menu = $this->em
            ->getRepository(Menu::class)
            ->findOneBy(['parent' => null]);

        $this->assertNotNull($menu);

        $this->client->request('GET', '/fr/admin/menu/' . $menu->getId() . '/edit');

        $this->client->submitForm(self::SAVE, [
            'menu[name]' => 'Updated Menu',
        ]);

        $this->assertResponseRedirects('/fr/admin/menu');
    }

    // -------------------------
    // NESTED STRUCTURE
    // -------------------------

    public function testNestedMenus(): void
    {
        $this->login();

        $em = $this->em;

        $root = new Menu();
        $root->setName('Root');

        $l1 = new Menu();
        $l1->setName('L1');

        $l2 = new Menu();
        $l2->setName('L2');

        // ✅ relation propre Doctrine
        $root->addChild($l1);
        $l1->addChild($l2);

        $em->persist($root);
        $em->flush();
        $em->clear();

        // 🔄 reload DB (IMPORTANT pour test réel)
        $repo = $em->getRepository(Menu::class);

        $rootDb = $repo->findOneBy(['name' => 'Root']);
        $l1Db = $repo->findOneBy(['name' => 'L1']);
        $l2Db = $repo->findOneBy(['name' => 'L2']);

        $this->assertEquals(0, $rootDb->getDepth());
        $this->assertEquals(1, $l1Db->getDepth());
        $this->assertEquals(2, $l2Db->getDepth());
    }
}
