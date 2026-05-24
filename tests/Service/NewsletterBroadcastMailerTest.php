<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\NewsletterBroadcastMailer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;

final class NewsletterBroadcastMailerTest extends TestCase
{
    public function testSendDeactivatesSubscribersAfterSuccessfulBroadcast(): void
    {
        $user = new User();
        $user->setEmail('abonne@example.org');
        $user->setPassword('hash');
        $user->setRoles([User::ROLE_NEWSLETTER]);
        $user->setActiveNewsletter(true);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findNewsletterRecipientsForSend')->willReturn([$user]);
        $userRepository->expects(self::once())
            ->method('deactivateNewsletterSubscribers')
            ->with(self::identicalTo([$user]));

        $mailer = new NewsletterBroadcastMailer(
            $this->createMock(MailerInterface::class),
            $userRepository,
            'admin@example.org',
        );

        $section = $this->createNewsletterSection();
        $result = $mailer->send($section, false);

        self::assertSame('success', $result['type']);
        self::assertSame(['abonne@example.org'], $result['sent_emails']);
        self::assertTrue($result['deactivated']);
    }

    public function testTestModeDoesNotDeactivateSubscribers(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findNewsletterRecipientsForSend')->willReturn([]);
        $userRepository->expects(self::never())->method('deactivateNewsletterSubscribers');

        $mailer = new NewsletterBroadcastMailer(
            $this->createMock(MailerInterface::class),
            $userRepository,
            'admin@example.org',
        );

        $result = $mailer->send($this->createNewsletterSection(), true);

        self::assertSame('warning', $result['type']);
        self::assertSame('newsletter.send.no_recipients', $result['message']);
    }

    private function createNewsletterSection(): Section
    {
        $template = new Template();
        $template->setCode('newsletter_template');
        $template->setName('Newsletter');
        $template->setType('newsletter_template');
        $template->setSummary('Newsletter');

        $section = new Section();
        $section->setTemplate($template);
        $post = new Post();
        $post->setName('Campagne');
        $post->setSection($section);
        $section->addPost($post);

        return $section;
    }
}
