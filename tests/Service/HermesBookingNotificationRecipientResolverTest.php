<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\ConfigRepository;
use App\Service\ConfigGlobalsProvider;
use App\Services\Booking\HermesBookingNotificationRecipientResolver;
use PHPUnit\Framework\TestCase;

final class HermesBookingNotificationRecipientResolverTest extends TestCase
{
    public function testUsesContactConfigEmail(): void
    {
        $configs = new ConfigGlobalsProvider(
            $this->configRepository(['contact' => 'site-contact@example.org']),
            [],
        );

        $resolver = new HermesBookingNotificationRecipientResolver($configs, 'admin@example.org');

        self::assertSame('site-contact@example.org', $resolver->resolveAdminEmail());
        self::assertSame('site-contact@example.org', $resolver->resolveFromEmail());
    }

    public function testFallsBackToHermesAdminEmail(): void
    {
        $configs = new ConfigGlobalsProvider(
            $this->configRepository([]),
            [],
        );

        $resolver = new HermesBookingNotificationRecipientResolver($configs, 'admin@example.org');

        self::assertSame('admin@example.org', $resolver->resolveAdminEmail());
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
