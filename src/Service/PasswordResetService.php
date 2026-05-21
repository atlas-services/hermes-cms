<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PasswordResetService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenGeneratorInterface $tokenGenerator,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
        #[Autowire('%hermes_admin_email%')]
        private readonly string $fromEmail,
    ) {
    }

    /**
     * Demande de réinitialisation : envoie un e-mail si le compte existe (sinon, silence).
     */
    public function requestPasswordReset(string $email, string $locale): void
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            return;
        }

        $token = $this->tokenGenerator->generateToken();
        $user->setResetToken($token);
        $this->entityManager->flush();

        $resetUrl = $this->urlGenerator->generate(
            'app_reset_password',
            ['_locale' => $locale, 'token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        try {
            $message = (new TemplatedEmail())
                ->from(new Address(
                    $this->fromEmail,
                    $this->translator->trans('security.email.reset_password.from_name', [], 'messages', $locale)
                ))
                ->to($user->getEmail())
                ->locale($locale)
                ->subject($this->translator->trans('security.email.reset_password.subject', [], 'messages', $locale))
                ->htmlTemplate('emails/reset_password.html.twig')
                ->context([
                    'resetUrl' => $resetUrl,
                    'userIdentifier' => $user->getUserIdentifier(),
                    'locale' => $locale,
                ]);

            $this->mailer->send($message);
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi e-mail réinitialisation mot de passe', [
                'email' => $user->getEmail(),
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function findUserByResetToken(string $token): ?User
    {
        return $this->userRepository->findOneBy(['resetToken' => $token]);
    }

    public function resetPassword(User $user, string $plainPassword): void
    {
        $user->setResetToken(null);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->entityManager->flush();
    }
}
