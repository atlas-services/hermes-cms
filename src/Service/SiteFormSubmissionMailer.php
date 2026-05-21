<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\FormTemplateKind;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SiteFormSubmissionMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        #[Autowire('%hermes_admin_email%')]
        private readonly string $adminEmail,
    ) {
    }

    /**
     * @param array<string, string|null> $fields
     */
    public function send(FormTemplateKind $kind, array $fields, string $locale, ?string $pageLabel = null): void
    {
        $subject = $this->translator->trans($kind->translationKey(), [
            '%site%' => $pageLabel ?? '',
        ], 'messages', $locale);

        $message = (new TemplatedEmail())
            ->from(new Address($this->adminEmail, 'Hermes'))
            ->to($this->adminEmail)
            ->locale($locale)
            ->subject($subject)
            ->htmlTemplate('emails/site_form_submission.html.twig')
            ->context([
                'kind' => $kind->value,
                'fields' => $fields,
                'pageLabel' => $pageLabel,
                'locale' => $locale,
            ]);

        $replyTo = $fields['email'] ?? null;
        if ($replyTo !== null && $replyTo !== '') {
            $message->replyTo($replyTo);
        }

        $this->mailer->send($message);
    }
}
