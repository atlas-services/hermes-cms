<?php

namespace App\DataFixtures;

use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PostFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Template par défaut
        $template = $manager->getRepository(Template::class)->findOneBy(['code' => 'libre']);

        if ($template === null) {
            $template = new Template();
            $template->setName('Libre');
            $template->setCode('libre');
            $template->setType('libre');
            $template->setSummary('Template Libre');

            $manager->persist($template);
        }

        // Menu avec sections pour les tests de section
        $menu = (new Menu())
            ->setName('Posts Menu')
            ->setPosition(1);

        $manager->persist($menu);

        // Section 1
        $section1 = (new Section())
            ->setMenu($menu)
            ->setTemplate($template)
            ->setPosition(1)
            ->setTemplateWidth(10);

        // Section 2
        $section2 = (new Section())
            ->setMenu($menu)
            ->setTemplate($template)
            ->setPosition(2)
            ->setTemplateWidth(10);

        $manager->persist($section1);
        $manager->persist($section2);

        // Posts pour section1
        $post1 = (new Post())
            ->setName('Post 1')
            ->setSection($section1)
            ->setPosition(1);

        $post2 = (new Post())
            ->setName('Post 2')
            ->setSection($section1)
            ->setPosition(2);

        // Posts pour section2
        $post3 = (new Post())
            ->setName('Post 3')
            ->setSection($section2)
            ->setPosition(1);

        $manager->persist($post1);
        $manager->persist($post2);
        $manager->persist($post3);

        // Menu leaf pour les tests de création via menu
        $menuLeaf = (new Menu())
            ->setName('Leaf Menu')
            ->setPosition(2);

        $manager->persist($menuLeaf);

        $manager->flush();
    }
}