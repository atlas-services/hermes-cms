<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

final class FrontContactFormTest extends WebTestCase
{
    public function testContactPagePostSendsMail(): void
    {
        $client = static::createClient();

        $client->request('POST', '/fr/contact', [
            'contact_form' => [
                'firstname' => 'Jean',
                'lastname' => 'Dupont',
                'email' => 'visitor@example.com',
                'telephone' => '0612345678',
                'message' => 'Message de test suffisamment long.',
            ],
            '_page_label' => 'Contact',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $session = $client->getRequest()->getSession();
        self::assertInstanceOf(FlashBagAwareSessionInterface::class, $session);
        $flashes = $session->getFlashBag()->all();
        self::assertArrayHasKey('success', $flashes);

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('alert-success', (string) $client->getResponse()->getContent());
    }
}
