<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Entity\Config;
use App\Service\ConfigGlobalsProvider;
use AtlasServices\HermesBookingBundle\Contract\BookingMailSignatureProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * Logo, titre et URL du site Hermes pour la signature des e-mails de réservation.
 */
final class HermesBookingMailSignatureProvider implements BookingMailSignatureProviderInterface
{
    public function __construct(
        private readonly ConfigGlobalsProvider $configGlobalsProvider,
        private readonly StorageInterface $vichStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'app.name')]
        private readonly string $appName,
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $defaultUri,
    ) {
    }

    public function getSignature(string $locale): array
    {
        $configs = $this->configGlobalsProvider->getConfigs();

        $siteName = $this->normalizeString($configs['title'] ?? null) ?? $this->appName;
        $siteUrl = $this->resolveSiteUrl($locale);
        $logoUrl = $this->resolveLogoUrl($configs['logo'] ?? null);

        return [
            'siteName' => $siteName !== '' ? $siteName : null,
            'siteUrl' => $siteUrl,
            'logoUrl' => $logoUrl,
        ];
    }

    private function resolveSiteUrl(string $locale): ?string
    {
        try {
            return $this->urlGenerator->generate(
                'front_home',
                ['_locale' => $locale],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        } catch (\Throwable) {
            $base = rtrim($this->defaultUri, '/');

            return $base.'/'.$locale.'/';
        }
    }

    private function resolveLogoUrl(mixed $logoConfig): ?string
    {
        if (!$logoConfig instanceof Config) {
            return null;
        }

        $fileName = $logoConfig->getFileName();
        if ($fileName === null || $fileName === '') {
            return null;
        }

        $relative = $this->vichStorage->resolveUri($logoConfig, 'imageFile');
        if ($relative === null || $relative === '') {
            return null;
        }

        return $this->toAbsoluteUrl($relative);
    }

    private function toAbsoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $base = rtrim($this->defaultUri, '/');

        return $base.'/'.ltrim($path, '/');
    }

    private function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' || $string === '~' ? null : $string;
    }
}
