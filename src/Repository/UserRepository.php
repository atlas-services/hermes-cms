<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * @return list<User>
     */
    public function findNewsletterSubscribers(bool $onlyActive = true): array
    {
        $users = $this->createQueryBuilder('u')
            ->orderBy('u.lastname', 'ASC')
            ->addOrderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();

        $filtered = [];
        foreach ($users as $user) {
            if (!$user instanceof User || !$user->hasNewsletterRole()) {
                continue;
            }
            if ($onlyActive && !$user->isActiveNewsletter()) {
                continue;
            }
            $filtered[] = $user;
        }

        return $filtered;
    }

    /**
     * @return list<User>
     */
    public function findNewsletterRecipientsForSend(bool $testMode = false): array
    {
        $role = $testMode ? User::ROLE_TEST_NEWSLETTER : User::ROLE_NEWSLETTER;
        $recipients = [];

        foreach ($this->createQueryBuilder('u')->getQuery()->getResult() as $user) {
            if (!$user instanceof User) {
                continue;
            }
            if (!\in_array($role, $user->getRoles(), true)) {
                continue;
            }
            if (!$testMode && !$user->isActiveNewsletter()) {
                continue;
            }
            $recipients[] = $user;
        }

        usort($recipients, static function (User $a, User $b): int {
            $cmp = strcmp((string) $a->getLastname(), (string) $b->getLastname());

            return $cmp !== 0 ? $cmp : strcmp((string) $a->getEmail(), (string) $b->getEmail());
        });

        return $recipients;
    }

    /**
     * @return list<string>
     */
    public function findNewsletterEmails(bool $testMode = false): array
    {
        $emails = [];
        foreach ($this->findNewsletterRecipientsForSend($testMode) as $user) {
            $email = $user->getEmail();
            if ($email !== null && $email !== '') {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * @param list<User> $users
     */
    public function deactivateNewsletterSubscribers(array $users): void
    {
        if ($users === []) {
            return;
        }

        foreach ($users as $user) {
            $user->setActiveNewsletter(false);
        }

        $this->getEntityManager()->flush();
    }

    public function switchNewsletterActive(int $id): User
    {
        $user = $this->find($id);
        if (!$user instanceof User) {
            throw new \InvalidArgumentException('User not found');
        }

        $user->setActiveNewsletter(!$user->isActiveNewsletter());
        $this->getEntityManager()->flush();

        return $user;
    }

    public function switchNewsletterActiveAll(bool $active): void
    {
        foreach ($this->findNewsletterSubscribers(false) as $user) {
            $user->setActiveNewsletter($active);
        }

        $this->getEntityManager()->flush();
    }
}
