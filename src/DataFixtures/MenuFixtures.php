<?php

namespace App\DataFixtures;

use App\Entity\Menu;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class MenuFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Racine 1
        $root1 = (new Menu())
            ->setName('Root 1')
            ->setPosition(1);

        // Racine 2
        $root2 = (new Menu())
            ->setName('Root 2')
            ->setPosition(2);

        // Enfants de root1
        $child1 = (new Menu())
            ->setName('Child 1')
            ->setPosition(1)
            ->setParent($root1);

        $child2 = (new Menu())
            ->setName('Child 2')
            ->setPosition(2)
            ->setParent($root1);

        $manager->persist($root1);
        $manager->persist($root2);
        $manager->persist($child1);
        $manager->persist($child2);

        $manager->flush();
    }
}
