<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\FormTemplateKind;
use App\Service\FrontFormSpamProtection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class FrontFormSpamProtectionTest extends TestCase
{
    public function testAcceptsValidSubmission(): void
    {
        $protection = $this->createProtection(minSeconds: 0);
        $fields = $protection->fields(FormTemplateKind::Contact);

        $request = $this->request([
            $fields['honeypot_name'] => '',
            $fields['time_name'] => $fields['started_at'],
            $fields['token_name'] => $fields['token'],
        ]);

        self::assertNull($protection->validate($request, FormTemplateKind::Contact));
    }

    public function testBlocksFilledHoneypot(): void
    {
        $protection = $this->createProtection(minSeconds: 0);
        $fields = $protection->fields(FormTemplateKind::Contact);

        $request = $this->request([
            $fields['honeypot_name'] => 'https://spam.example',
            $fields['time_name'] => $fields['started_at'],
            $fields['token_name'] => $fields['token'],
        ]);

        self::assertSame(FrontFormSpamProtection::REASON_HONEYPOT, $protection->validate($request, FormTemplateKind::Contact));
    }

    public function testBlocksTooFastSubmission(): void
    {
        $protection = $this->createProtection(minSeconds: 3);
        $fields = $protection->fields(FormTemplateKind::Contact);

        $request = $this->request([
            $fields['honeypot_name'] => '',
            $fields['time_name'] => $fields['started_at'],
            $fields['token_name'] => $fields['token'],
        ]);

        self::assertSame(FrontFormSpamProtection::REASON_TOO_FAST, $protection->validate($request, FormTemplateKind::Contact));
    }

    public function testBlocksAfterRateLimitIsReached(): void
    {
        $protection = $this->createProtection(minSeconds: 0, limit: 1);
        $fields = $protection->fields(FormTemplateKind::Contact);
        $payload = [
            $fields['honeypot_name'] => '',
            $fields['time_name'] => $fields['started_at'],
            $fields['token_name'] => $fields['token'],
        ];

        self::assertNull($protection->validate($this->request($payload), FormTemplateKind::Contact));
        self::assertSame(FrontFormSpamProtection::REASON_RATE_LIMIT, $protection->validate($this->request($payload), FormTemplateKind::Contact));
    }

    private function createProtection(int $minSeconds, int $limit = 5): FrontFormSpamProtection
    {
        return new FrontFormSpamProtection(
            new RateLimiterFactory([
                'id' => 'test_front_form',
                'policy' => 'sliding_window',
                'limit' => $limit,
                'interval' => '10 minutes',
            ], new InMemoryStorage()),
            new NullLogger(),
            true,
            'website_url',
            $minSeconds,
            7200,
            'test-secret',
        );
    }

    /**
     * @param array<string, string> $payload
     */
    private function request(array $payload): Request
    {
        $request = Request::create('/fr/form/contact', 'POST', $payload);
        $request->server->set('REMOTE_ADDR', '203.0.113.42');

        return $request;
    }
}
