<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

final class FrontFormContactRouteTest extends WebTestCase
{
    public function testFormContactPostHitsFormControllerNotMenu(): void
    {
        $client = static::createClient();

        $client->request('POST', '/fr/form/contact', [
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
        self::assertNotSame(404, $client->getResponse()->getStatusCode());

        $session = $client->getRequest()->getSession();
        self::assertInstanceOf(FlashBagAwareSessionInterface::class, $session);
        $flashes = $session->getFlashBag()->all();
        self::assertArrayHasKey('success', $flashes, 'Expected mail success flash, got: '.json_encode($flashes));
    }
}
