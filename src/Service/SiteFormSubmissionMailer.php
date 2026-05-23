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
        private readonly ConfigGlobalsProvider $configGlobalsProvider,
        #[Autowire('%hermes_admin_email%')]
        private readonly string $adminEmail,
        #[Autowire('%hermes_newsletter_email%')]
        private readonly string $newsletterEmail,
    ) {
    }

    /**
     * @param array<string, string|null> $fields
     */
    public function send(FormTemplateKind $kind, array $fields, string $locale, ?string $pageLabel = null): void
    {
        $recipient = $this->resolveRecipient($kind);
        $subject = $this->translator->trans($kind->translationKey(), [
            '%site%' => $pageLabel ?? '',
        ], 'messages', $locale);

        $message = (new TemplatedEmail())
            ->from(new Address($this->adminEmail, 'Hermes'))
            ->to($recipient)
            ->locale($locale)
            ->subject($subject)
            ->htmlTemplate($kind->mailTemplate())
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

    private function resolveRecipient(FormTemplateKind $kind): string
    {
        $configs = $this->configGlobalsProvider->getConfigs();

        return match ($kind) {
            FormTemplateKind::Contact => $this->firstEmail(
                $configs['contact'] ?? null,
                $this->adminEmail,
            ),
            FormTemplateKind::Newsletter => $this->firstEmail(
                $configs['newsletter_contact'] ?? null,
                $this->newsletterEmail,
                $this->adminEmail,
            ),
            FormTemplateKind::Livredor => $this->firstEmail(
                $configs['livredor_contact'] ?? null,
                $this->adminEmail,
            ),
        };
    }

    private function firstEmail(mixed ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $email = $this->normalizeEmail($candidate);
            if ($email !== null) {
                return $email;
            }
        }

        return $this->adminEmail;
    }

    private function normalizeEmail(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $email = trim((string) $value);
        if ($email === '' || $email === '~') {
            return null;
        }

        return filter_var($email, \FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
