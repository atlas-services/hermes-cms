<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Service\ConfigGlobalsProvider;
use AtlasServices\HermesBookingBundle\Contract\BookingNotificationRecipientResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Même logique que {@see \App\Service\SiteFormSubmissionMailer} : config « contact » puis admin Hermes.
 */
final class HermesBookingNotificationRecipientResolver implements BookingNotificationRecipientResolverInterface
{
    public function __construct(
        private readonly ConfigGlobalsProvider $configGlobalsProvider,
        #[Autowire('%hermes_admin_email%')]
        private readonly string $adminEmail,
    ) {
    }

    public function resolveAdminEmail(): string
    {
        $configs = $this->configGlobalsProvider->getConfigs();

        return $this->firstEmail(
            $configs['contact'] ?? null,
            $this->adminEmail,
        );
    }

    public function resolveFromEmail(): string
    {
        return $this->resolveAdminEmail();
    }

    private function firstEmail(mixed ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $email = $this->normalizeEmail($candidate);
            if ($email !== null) {
                return $email;
            }
        }

        return $this->adminEmail;
    }

    private function normalizeEmail(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $email = trim((string) $value);
        if ($email === '' || $email === '~') {
            return null;
        }

        return filter_var($email, \FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
