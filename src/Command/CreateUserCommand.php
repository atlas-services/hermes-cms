<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-user')]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Creates or updates the admin user with the configured email and password.')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'The email of the user',  $_ENV['ADMIN_EMAIL'])
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'The password of the user', $_ENV['ADMIN_PASSWORD']);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getOption('email') ?: $_ENV['ADMIN_EMAIL'];
        $password = $input->getOption('password') ?: $_ENV['ADMIN_PASSWORD'];

        if (!$email || !$password) {
            throw new InvalidArgumentException('Email and password must be provided.');
        }

        $message = $this->initAdminUser($email, $password);

        $output->writeln(sprintf('%s with email %s', $message, $email));

        return Command::SUCCESS;
    }

    public function initAdminUser(?string $email, ?string $password): string
    {
        $admin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (is_null($admin)) {
            $admin = new User();
            $admin->setEmail($email);
            $admin->setRoles(['ROLE_ADMIN']);
            $admin->setIsVerified(true);
            $this->entityManager->persist($admin);
            $message = 'Admin user created';
        } else {
            $message = 'Admin user updated';
        }

        $admin->setPassword($this->hasher->hashPassword($admin, $password));
        $this->entityManager->flush();

        return $message;
    }
}
