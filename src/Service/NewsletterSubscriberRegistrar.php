<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Inscription front newsletter → utilisateur ROLE_NEWSLETTER (Hermes 2.2.7).
 */
final class NewsletterSubscriberRegistrar
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @param array{firstname?: string|null, lastname?: string|null, email?: string|null} $fields
     */
    public function registerFromForm(array $fields): void
    {
        $email = trim((string) ($fields['email'] ?? ''));
        if ($email === '' || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $firstname = trim((string) ($fields['firstname'] ?? ''));
        $lastname = trim((string) ($fields['lastname'] ?? ''));

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $user = new User();
            $user->setEmail($email);
            $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(24))));
            $user->setIsVerified(true);
            $user->setRoles([User::ROLE_NEWSLETTER]);
        } elseif (!$user->hasNewsletterRole()) {
            $stored = $user->getRoles();
            $stored = array_values(array_filter($stored, static fn (string $r): bool => $r !== 'ROLE_USER'));
            $stored[] = User::ROLE_NEWSLETTER;
            $user->setRoles(array_values(array_unique($stored)));
        }

        if ($firstname !== '') {
            $user->setFirstname($firstname);
        }
        if ($lastname !== '') {
            $user->setLastname($lastname);
        }
        $user->setActiveNewsletter(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
