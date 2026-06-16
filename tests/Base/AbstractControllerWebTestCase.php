<?php

namespace App\Tests\Base;

use App\DataFixtures\UserFixtures;
use App\Entity\Config;
use App\Entity\Template;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @codeCoverageIgnore
 */
abstract class AbstractControllerWebTestCase extends WebTestCase
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

        $executor = new ORMExecutor(
            $this->em,
            new ORMPurger($this->em, $this->fixturePurgerExcludedTables()),
        );

        $executor->execute($loader->getFixtures());
        $this->em->clear();
    }

    /**
     * @return list<string>
     */
    private function fixturePurgerExcludedTables(): array
    {
        $platform = $this->em->getConnection()->getDatabasePlatform();
        $quoteStrategy = $this->em->getConfiguration()->getQuoteStrategy();
        $schemaManager = $this->em->getConnection()->createSchemaManager();

        $excluded = [
            $quoteStrategy->getTableName($this->em->getClassMetadata(Template::class), $platform),
            $quoteStrategy->getTableName($this->em->getClassMetadata(Config::class), $platform),
        ];

        if (!class_exists(\AtlasServices\HermesBookingBundle\HermesBookingBundle::class)) {
            return $excluded;
        }

        foreach ($this->em->getMetadataFactory()->getAllMetadata() as $metadata) {
            if (!str_starts_with($metadata->getName(), 'AtlasServices\\HermesBookingBundle\\Entity\\')) {
                continue;
            }

            $table = $metadata->getTableName();
            if ($schemaManager->tablesExist([$table])) {
                continue;
            }

            $quoted = $quoteStrategy->getTableName($metadata, $platform);
            $excluded[] = $quoted;
            $excluded[] = '"'.$table.'"';
            $excluded[] = $table;
        }

        return array_values(array_unique($excluded));
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
