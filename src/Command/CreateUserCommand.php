<?php

namespace App\Command;

use App\Entity\User; // Assurez-vous d'importer votre entité User
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Exception\InvalidArgumentException;

#[AsCommand(name: 'app:create-user')]
class CreateUserCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Creates a new user with a predefined email and password.')
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

        $output->writeln( sprintf("%s with email %s ", $message, $email));

        return Command::SUCCESS;
    }

    public function initAdminUser(?string $email, ?string $password) : string
    {
        $message = 'user Admin exists.';
        $admin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if(is_null($admin)){
            // Créer une instance de l'utilisateur
            $user = new User();
            $user->setEmail($email);
            $user->setRoles(['ROLE_ADMIN']);
            $user->setPassword(password_hash($password, PASSWORD_BCRYPT)); // Assurez-vous d'utiliser votre méthode de hashage

            // Enregistrer l'utilisateur dans la base de données
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $message = 'Admin user created';
        }

        $this->entityManager->flush();
        return $message ;
    }
}
