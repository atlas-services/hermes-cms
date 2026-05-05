<?php

namespace App\Tests\Base;

use App\DataFixtures\UserFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @codeCoverageIgnore
 */
abstract class BaseControllerTest extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient([
            'debug' => false,
        ]);

        $this->client->disableReboot(); // IMPORTANT pour garder session

        $this->em = self::getContainer()->get('doctrine')->getManager();

        $this->loadFixtures();
    }

    protected function loadFixtures(): void
    {
        $loader = new Loader();
        $loader->addFixture(new UserFixtures(
            self::getContainer()->get('security.user_password_hasher')
        ));

        $purger = new ORMPurger($this->em);
        $executor = new ORMExecutor($this->em, $purger);

        $executor->execute($loader->getFixtures());
        $this->em->clear();
    }

    protected function tearDown(): void
    {
        $this->em->clear();
        parent::tearDown();
    }

    protected function login(): void
    {
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/fr/login');

        $this->client->submitForm('login_submit', [
            '_username' => UserFixtures::ADMIN_EMAIL,
            '_password' => UserFixtures::ADMIN_PASS,
        ]);

        $this->client->followRedirect();
    }
}
