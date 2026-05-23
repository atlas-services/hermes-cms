<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class FrontContactFormValidationTest extends WebTestCase
{
    public function testInvalidSubmissionRedisplaysFieldErrors(): void
    {
        $client = static::createClient();

        $client->request('POST', '/fr/form/contact', [
            'contact_form' => [
                'firstname' => 'Jean',
                'lastname' => 'Dupont',
                'email' => 'test@test.fr',
                'telephone' => '0612345678',
                'message' => 'court',
            ],
            '_page_label' => 'Contact',
            '_redirect' => '/fr/contact',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $crawler = $client->followRedirect();
        self::assertStringContainsString('alert-danger', (string) $client->getResponse()->getContent());
        self::assertInputValueSame('contact_form[telephone]', '0612345678');
        self::assertStringContainsString('Le message est trop court', (string) $client->getResponse()->getContent());
    }
}
