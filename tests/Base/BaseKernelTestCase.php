<?php

namespace App\Tests\Base;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

abstract class BaseKernelTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initDatabase();
    }
    public function initDatabase(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();

        $this->em = $em; // 🔥 IMPORTANT MANQUANT

        // 1. reset schema
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();

        if (!empty($metadata)) {
            $schemaTool->dropSchema($metadata);
            $schemaTool->createSchema($metadata);
        }

        // 2. init templates + config
        $application = new Application(self::$kernel);
        $command = $application->find('app:init-hermes');

        $tester = new CommandTester($command);
        $tester->execute([]);

        // 3. fixtures
        $executor = new ORMExecutor(
            $em,
            new ORMPurger($em)
        );

        $fixtures = $this->loadFixtures();

        if (count($fixtures) > 0) {
            $executor->execute($fixtures);
        }

        $em->clear();
    }

    /**
     * @return array<int, FixtureInterface>
     */
    protected function loadFixtures(): array
    {
        return [];
    }
}
