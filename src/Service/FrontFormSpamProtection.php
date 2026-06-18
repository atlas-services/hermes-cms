<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\FormTemplateKind;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Protection légère sans service externe : honeypot, temps minimum et limite par IP.
 */
final class FrontFormSpamProtection
{
    public const REASON_HONEYPOT = 'honeypot';
    public const REASON_TIMESTAMP = 'timestamp';
    public const REASON_TOO_FAST = 'too_fast';
    public const REASON_TOO_OLD = 'too_old';
    public const REASON_RATE_LIMIT = 'rate_limit';

    private const TIME_FIELD = '_hermes_form_started_at';
    private const TOKEN_FIELD = '_hermes_form_token';

    public function __construct(
        #[Autowire(service: 'limiter.front_form')]
        private readonly RateLimiterFactory $rateLimiterFactory,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(bool:HERMES_FORM_SPAM_PROTECTION_ENABLED)%')]
        private readonly bool $enabled,
        #[Autowire('%env(HERMES_FORM_HONEYPOT_FIELD)%')]
        private readonly string $honeypotField,
        #[Autowire('%env(int:HERMES_FORM_MIN_SECONDS)%')]
        private readonly int $minSeconds,
        #[Autowire('%env(int:HERMES_FORM_MAX_AGE_SECONDS)%')]
        private readonly int $maxAgeSeconds,
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    /**
     * @return array{honeypot_name: string, time_name: string, token_name: string, started_at: string, token: string}
     */
    public function fields(FormTemplateKind $kind): array
    {
        $startedAt = (string) time();

        return [
            'honeypot_name' => $this->honeypotField,
            'time_name' => self::TIME_FIELD,
            'token_name' => self::TOKEN_FIELD,
            'started_at' => $startedAt,
            'token' => $this->sign($kind, $startedAt),
        ];
    }

    public function validate(Request $request, FormTemplateKind $kind): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $limiter = $this->rateLimiterFactory->create($this->limiterKey($request, $kind));
        $limit = $limiter->consume(1);
        if (!$limit->isAccepted()) {
            $this->logBlocked($request, $kind, self::REASON_RATE_LIMIT);

            return self::REASON_RATE_LIMIT;
        }

        if (trim($request->request->getString($this->honeypotField)) !== '') {
            $this->logBlocked($request, $kind, self::REASON_HONEYPOT);

            return self::REASON_HONEYPOT;
        }

        $startedAt = $request->request->getString(self::TIME_FIELD);
        $token = $request->request->getString(self::TOKEN_FIELD);
        if (!$this->isValidTimestamp($startedAt) || !hash_equals($this->sign($kind, $startedAt), $token)) {
            $this->logBlocked($request, $kind, self::REASON_TIMESTAMP);

            return self::REASON_TIMESTAMP;
        }

        $age = time() - (int) $startedAt;
        if ($age < $this->minSeconds) {
            $this->logBlocked($request, $kind, self::REASON_TOO_FAST, ['age' => $age]);

            return self::REASON_TOO_FAST;
        }

        if ($age > $this->maxAgeSeconds) {
            $this->logBlocked($request, $kind, self::REASON_TOO_OLD, ['age' => $age]);

            return self::REASON_TOO_OLD;
        }

        return null;
    }

    private function sign(FormTemplateKind $kind, string $startedAt): string
    {
        return hash_hmac('sha256', $kind->value.'|'.$startedAt, $this->secret);
    }

    private function isValidTimestamp(string $value): bool
    {
        if ($value === '' || !ctype_digit($value)) {
            return false;
        }

        $timestamp = (int) $value;

        return $timestamp > 0 && $timestamp <= time();
    }

    private function limiterKey(Request $request, FormTemplateKind $kind): string
    {
        return $kind->value.'|'.($request->getClientIp() ?? 'unknown');
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logBlocked(Request $request, FormTemplateKind $kind, string $reason, array $context = []): void
    {
        $this->logger->warning('Front form spam protection blocked a submission.', array_merge([
            'kind' => $kind->value,
            'reason' => $reason,
            'ip' => $request->getClientIp(),
        ], $context));
    }
}
