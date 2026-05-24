<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Post;
use App\Entity\Section;
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
     * @return array{type: string, message: string}
     */
    public function send(Section $section, bool $testMode = false): array
    {
        $template = $section->getTemplate();
        if ($template === null || $template->getCode() !== 'newsletter_template') {
            return ['type' => 'danger', 'message' => 'newsletter.send.not_template'];
        }

        $recipients = $this->userRepository->findNewsletterEmails($testMode);
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

        $sent = 0;
        $errors = 0;
        foreach ($recipients as $recipient) {
            try {
                $message = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, 'Hermes'))
                    ->to($recipient)
                    ->subject($subject)
                    ->htmlTemplate('newsletter/newsletter.html.twig')
                    ->context(['section' => $section]);

                $this->mailer->send($message);
                ++$sent;
            } catch (TransportExceptionInterface) {
                ++$errors;
            }
        }

        if ($sent === 0) {
            return ['type' => 'danger', 'message' => 'newsletter.send.failed'];
        }

        if ($errors > 0) {
            return [
                'type' => 'warning',
                'message' => 'newsletter.send.partial',
                'count' => $sent,
                'errors' => $errors,
            ];
        }

        return ['type' => 'success', 'message' => 'newsletter.send.success', 'count' => $sent];
    }
}
