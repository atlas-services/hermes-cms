<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\HermesBookingRouteGuardSubscriber;
use App\Services\Booking\HermesBookingStatus;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class HermesBookingRouteGuardSubscriberTest extends TestCase
{
    public function testBlocksBookingPathWhenModuleInactive(): void
    {
        if (!class_exists(\AtlasServices\HermesBookingBundle\HermesBookingBundle::class)) {
            self::markTestSkipped('HermesBookingBundle is not installed.');
        }

        $_ENV['HERMES_BOOKING_ENABLED'] = '1';
        $_SERVER['HERMES_BOOKING_ENABLED'] = '1';

        $subscriber = new HermesBookingRouteGuardSubscriber($this->createStatus(false));

        $request = Request::create('/admin/booking');

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        try {
            $this->expectException(NotFoundHttpException::class);
            $subscriber($event);
        } finally {
            unset($_ENV['HERMES_BOOKING_ENABLED'], $_SERVER['HERMES_BOOKING_ENABLED']);
        }
    }

    public function testBlocksBookingRouteWhenModuleInactive(): void
    {
        if (!class_exists(\AtlasServices\HermesBookingBundle\HermesBookingBundle::class)) {
            self::markTestSkipped('HermesBookingBundle is not installed.');
        }

        $_ENV['HERMES_BOOKING_ENABLED'] = '1';
        $_SERVER['HERMES_BOOKING_ENABLED'] = '1';

        $subscriber = new HermesBookingRouteGuardSubscriber($this->createStatus(false));

        $request = Request::create('/admin/booking');
        $request->attributes->set('_route', 'hermes_booking_admin_index');

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        try {
            $this->expectException(NotFoundHttpException::class);
            $subscriber($event);
        } finally {
            unset($_ENV['HERMES_BOOKING_ENABLED'], $_SERVER['HERMES_BOOKING_ENABLED']);
        }
    }

    #[DoesNotPerformAssertions]
    public function testAllowsBookingRouteWhenModuleActive(): void
    {
        if (!class_exists(\AtlasServices\HermesBookingBundle\HermesBookingBundle::class)) {
            self::markTestSkipped('HermesBookingBundle is not installed.');
        }

        $_ENV['HERMES_BOOKING_ENABLED'] = '1';
        $_SERVER['HERMES_BOOKING_ENABLED'] = '1';

        $subscriber = new HermesBookingRouteGuardSubscriber($this->createStatus(true));

        $request = Request::create('/admin/booking');
        $request->attributes->set('_route', 'hermes_booking_admin_index');

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        try {
            $subscriber($event);
        } finally {
            unset($_ENV['HERMES_BOOKING_ENABLED'], $_SERVER['HERMES_BOOKING_ENABLED']);
        }
    }

    private function createStatus(bool $schemaReady): HermesBookingStatus
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn($schemaReady);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        return new HermesBookingStatus($connection);
    }
}
