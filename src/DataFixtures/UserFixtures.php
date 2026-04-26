<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const ADMIN_EMAIL = 'admin@test.com';
    public const ADMIN_PASS = 'password';

    public function __construct(
        private UserPasswordHasherInterface $hasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail(self::ADMIN_EMAIL);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword(
            $this->hasher->hashPassword($user, self::ADMIN_PASS)
        );
        $user->setIsVerified(true);

        $manager->persist($user);
        $manager->flush();
    }
}
