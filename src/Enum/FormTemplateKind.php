<?php

declare(strict_types=1);

namespace App\Enum;

enum FormTemplateKind: string
{
    case Contact = 'contact';
    case Newsletter = 'newsletter';
    case Livredor = 'livredor';

    public function translationKey(): string
    {
        return match ($this) {
            self::Contact => 'form.contact.mail_subject',
            self::Newsletter => 'form.newsletter.mail_subject',
            self::Livredor => 'form.livredor.mail_subject',
        };
    }

    public function mailTemplate(): string
    {
        return match ($this) {
            self::Contact => 'emails/email_contact.html.twig',
            self::Newsletter => 'emails/email_newsletter.html.twig',
            self::Livredor => 'emails/email_livredor.html.twig',
        };
    }

    public static function tryFromTemplateCode(?string $code): ?self
    {
        if ($code === null || $code === '') {
            return null;
        }

        return self::tryFrom(strtolower(trim($code)));
    }
}
