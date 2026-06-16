<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Services\Booking\HermesBookingStatus;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use PHPUnit\Framework\TestCase;

final class HermesBookingStatusTest extends TestCase
{
    public function testIsActiveRequiresDatabaseSchemaWhenBundleInstalled(): void
    {
        if (!class_exists(\AtlasServices\HermesBookingBundle\HermesBookingBundle::class)) {
            self::markTestSkipped('HermesBookingBundle is not installed.');
        }

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $status = new HermesBookingStatus($connection);

        $_ENV['HERMES_BOOKING_ENABLED'] = '1';
        $_SERVER['HERMES_BOOKING_ENABLED'] = '1';

        self::assertFalse($status->hasDatabaseSchema());
        self::assertFalse($status->isActive());

        unset($_ENV['HERMES_BOOKING_ENABLED'], $_SERVER['HERMES_BOOKING_ENABLED']);
    }

    public function testIsActiveWhenSchemaPresent(): void
    {
        if (!class_exists(\AtlasServices\HermesBookingBundle\HermesBookingBundle::class)) {
            self::markTestSkipped('HermesBookingBundle is not installed.');
        }

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $status = new HermesBookingStatus($connection);

        $_ENV['HERMES_BOOKING_ENABLED'] = '1';
        $_SERVER['HERMES_BOOKING_ENABLED'] = '1';

        self::assertTrue($status->hasDatabaseSchema());
        self::assertTrue($status->isActive());

        unset($_ENV['HERMES_BOOKING_ENABLED'], $_SERVER['HERMES_BOOKING_ENABLED']);
    }
}
