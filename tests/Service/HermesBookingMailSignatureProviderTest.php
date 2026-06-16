<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Config;
use App\Repository\ConfigRepository;
use App\Service\ConfigGlobalsProvider;
use App\Services\Booking\HermesBookingMailSignatureProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

final class HermesBookingMailSignatureProviderTest extends TestCase
{
    public function testBuildsSignatureFromHermesConfig(): void
    {
        $logo = $this->createMock(Config::class);
        $logo->method('getFileName')->willReturn('logo.png');

        $configs = new ConfigGlobalsProvider(
            $this->configRepository([
                'title' => 'Mon restaurant',
                'logo' => $logo,
            ]),
            [],
        );

        $storage = $this->createMock(StorageInterface::class);
        $storage->method('resolveUri')->with($logo, 'imageFile')->willReturn('/uploads/app/logo.png');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://example.org/fr/');

        $provider = new HermesBookingMailSignatureProvider(
            $configs,
            $storage,
            $urlGenerator,
            'app',
            'https://example.org',
        );

        self::assertSame([
            'siteName' => 'Mon restaurant',
            'siteUrl' => 'https://example.org/fr/',
            'logoUrl' => 'https://example.org/uploads/app/logo.png',
        ], $provider->getSignature('fr'));
    }

    public function testFallsBackToAppNameWhenTitleMissing(): void
    {
        $configs = new ConfigGlobalsProvider(
            $this->configRepository([]),
            [],
        );

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://example.org/fr/');

        $provider = new HermesBookingMailSignatureProvider(
            $configs,
            $this->createMock(StorageInterface::class),
            $urlGenerator,
            'jazzenville',
            'https://example.org',
        );

        $signature = $provider->getSignature('fr');

        self::assertSame('jazzenville', $signature['siteName']);
        self::assertNull($signature['logoUrl']);
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
