<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\FormTemplateKind;
use App\Repository\ConfigRepository;
use App\Service\ConfigGlobalsProvider;
use App\Service\SiteFormSubmissionMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SiteFormSubmissionMailerTest extends TestCase
{
    public function testContactMailUsesConfigRecipient(): void
    {
        $sent = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(static function (TemplatedEmail $email) use (&$sent): void {
            $sent = $email;
        });

        $configs = new ConfigGlobalsProvider(
            $this->configRepository(['contact' => 'site-contact@example.org']),
            [],
        );

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Sujet test');

        $service = new SiteFormSubmissionMailer(
            $mailer,
            $translator,
            $configs,
            'admin@example.org',
            'newsletter@example.org',
        );

        $service->send(FormTemplateKind::Contact, [
            'firstname' => 'A',
            'lastname' => 'B',
            'email' => 'visitor@example.com',
            'telephone' => '0600000000',
            'message' => 'Hello',
        ], 'fr', 'Ma page');

        self::assertInstanceOf(TemplatedEmail::class, $sent);
        self::assertSame('emails/email_contact.html.twig', $sent->getHtmlTemplate());
        self::assertSame(['site-contact@example.org'], array_map(static fn ($a) => $a->getAddress(), $sent->getTo()));
        self::assertSame(['visitor@example.com'], array_map(static fn ($a) => $a->getAddress(), $sent->getReplyTo()));
    }

    public function testNewsletterMailUsesNewsletterConfigRecipient(): void
    {
        $sent = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(static function (TemplatedEmail $email) use (&$sent): void {
            $sent = $email;
        });

        $configs = new ConfigGlobalsProvider(
            $this->configRepository(['newsletter_contact' => 'inscriptions@example.org']),
            [],
        );

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Newsletter');

        $service = new SiteFormSubmissionMailer(
            $mailer,
            $translator,
            $configs,
            'admin@example.org',
            'newsletter-fallback@example.org',
        );

        $service->send(FormTemplateKind::Newsletter, [
            'firstname' => 'A',
            'lastname' => 'B',
            'email' => 'visitor@example.com',
        ], 'fr');

        self::assertInstanceOf(TemplatedEmail::class, $sent);
        self::assertSame(['inscriptions@example.org'], array_map(static fn ($a) => $a->getAddress(), $sent->getTo()));
    }

    /**
     * @param array<string, mixed> $active
     */
    private function configRepository(array $active): ConfigRepository
    {
        $repo = $this->createMock(ConfigRepository::class);
        $repo->method('getActiveConfig')->willReturn($active);

        return $repo;
    }
}
