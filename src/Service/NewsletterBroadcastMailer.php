<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Post;
use App\Entity\Section;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class NewsletterBroadcastMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $userRepository,
        #[Autowire('%hermes_admin_email%')]
        private readonly string $fromEmail,
    ) {
    }

    /**
     * @return array{
     *     type: string,
     *     message: string,
     *     count?: int,
     *     errors?: int,
     *     sent_emails?: list<string>,
     *     deactivated?: bool
     * }
     */
    public function send(Section $section, bool $testMode = false): array
    {
        $template = $section->getTemplate();
        if ($template === null || $template->getCode() !== 'newsletter_template') {
            return ['type' => 'danger', 'message' => 'newsletter.send.not_template'];
        }

        $recipients = $this->userRepository->findNewsletterRecipientsForSend($testMode);
        if ($recipients === []) {
            return ['type' => 'warning', 'message' => 'newsletter.send.no_recipients'];
        }

        $subject = 'Newsletter';
        $firstPost = $section->getPosts()->first();
        if ($firstPost instanceof Post && $firstPost->getName() !== '') {
            $subject = $firstPost->getName();
        }
        if ($testMode) {
            $subject .= ' (Test)';
        }

        /** @var list<User> $sentUsers */
        $sentUsers = [];
        /** @var list<string> $sentEmails */
        $sentEmails = [];
        $errors = 0;

        foreach ($recipients as $user) {
            $email = $user->getEmail();
            if ($email === null || $email === '') {
                continue;
            }

            try {
                $message = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, 'Hermes'))
                    ->to($email)
                    ->subject($subject)
                    ->htmlTemplate('newsletter/newsletter.html.twig')
                    ->context(['section' => $section]);

                $this->mailer->send($message);
                $sentUsers[] = $user;
                $sentEmails[] = $email;
            } catch (TransportExceptionInterface) {
                ++$errors;
            }
        }

        $sent = \count($sentEmails);

        if ($sent === 0) {
            return ['type' => 'danger', 'message' => 'newsletter.send.failed'];
        }

        $deactivated = false;
        if (!$testMode && $sentUsers !== []) {
            $this->userRepository->deactivateNewsletterSubscribers($sentUsers);
            $deactivated = true;
        }

        $base = [
            'sent_emails' => $sentEmails,
            'count' => $sent,
            'deactivated' => $deactivated,
        ];

        if ($errors > 0) {
            return [
                ...$base,
                'type' => 'warning',
                'message' => 'newsletter.send.partial',
                'errors' => $errors,
            ];
        }

        return [
            ...$base,
            'type' => 'success',
            'message' => 'newsletter.send.success',
        ];
    }
}
