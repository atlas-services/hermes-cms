<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\HermesApiClient;
use App\Service\HermesEncryptionService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class HermesApiClientTest extends TestCase
{
    private static function requestStackWithSession(): RequestStack
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('https://cms.example/fr/admin');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        return $stack;
    }

    /** @param array<string, mixed> $options */
    private static function headerLinesContaining(array $options, string $needle): string
    {
        $lines = [];
        foreach ($options['headers'] ?? [] as $h) {
            if (\is_string($h) && str_contains(strtolower($h), strtolower($needle))) {
                $lines[] = $h;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param callable(string, string, array): MockResponse|null $factory
     */
    private static function client(
        MockHttpClient $http,
        RequestStack $stack,
        string $base = 'https://api.example.com',
        string $templates = '',
        string $overridePath = '',
        string $email = '',
        string $password = '',
        bool $notJwt = true,
        bool $isCms = true,
        string $catalogEntity = 'templates',
    ): HermesApiClient {
        return new HermesApiClient(
            httpClient: $http,
            requestStack: $stack,
            encryptionService: new HermesEncryptionService(),
            hermesApiBaseUri: $base,
            hermesApiTemplatesUri: $templates,
            hermesApiLibreCatalogPath: $overridePath,
            hermesApiEmail: $email,
            hermesApiPassword: $password,
            hermesApiNotJwtVersion: $notJwt,
            hermesApiIsCms: $isCms,
            hermesApiCatalogEntity: $catalogEntity,
            logger: new NullLogger(),
        );
    }

    public function testCatalogDisabledWhenNoHermesEnv(): void
    {
        $client = self::client(new MockHttpClient(), self::requestStackWithSession(), '', '', '', '', '', true, true, 'templates');

        self::assertFalse($client->isLibreCatalogConfigured());
        self::assertSame([], $client->fetchLibreTemplateSummaries());
    }

    public function testCatalogUsesBaseAndDefaultPathWhenTemplatesEmpty(): void
    {
        $payload = [
            'hydra:member' => [
                ['@id' => '/api/templates/1', 'name' => 'Bloc A'],
            ],
        ];
        $seenUrl = null;
        $authorization = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options = []) use (&$seenUrl, &$authorization, $payload): MockResponse {
            $seenUrl = $url;
            self::assertSame('GET', $method);
            $authorization = self::headerLinesContaining($options, 'authorization');

            return new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });
        $svc = self::client($http, self::requestStackWithSession(), 'https://api.example.com');
        $items = $svc->fetchLibreTemplateSummaries();

        self::assertStringContainsStringIgnoringCase('bearer azeaezaeaz', $authorization ?? '');

        self::assertSame('https://api.example.com/api/templates?itemsPerPage=50', $seenUrl);
        self::assertCount(1, $items);
        self::assertSame('Bloc A', $items[0]['label']);
    }

    public function testCatalogUsesApiHermesTemplatesPrefixLikeHermes227(): void
    {
        $payload = [
            'hydra:member' => [
                ['@id' => '/api/templates/2', 'name' => 'Bloc B'],
            ],
        ];
        $seenUrl = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options = []) use (&$seenUrl, $payload): MockResponse {
            $seenUrl = $url;

            return new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });
        $svc = self::client(
            $http,
            self::requestStackWithSession(),
            '',
            'https://api.example.com/api/',
        );
        $items = $svc->fetchLibreTemplateSummaries();

        self::assertSame('https://api.example.com/api/templates?itemsPerPage=50', $seenUrl);
        self::assertCount(1, $items);
        self::assertSame('https://api.example.com/api/templates/2', $items[0]['iri']);
    }

    public function testCatalogParsesHydraMemberWithExplicitOverride(): void
    {
        $payload = [
            'hydra:member' => [
                ['@id' => '/api/libre_templates/1', 'name' => 'Bloc A'],
            ],
        ];
        $http = new MockHttpClient([
            new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]);
        $svc = self::client($http, self::requestStackWithSession(), 'https://api.example.com', '', '/api/libre_templates');
        $items = $svc->fetchLibreTemplateSummaries();

        self::assertCount(1, $items);
        self::assertSame('Bloc A', $items[0]['label']);
        self::assertSame('https://api.example.com/api/libre_templates/1', $items[0]['iri']);
    }

    public function testFetchHtmlUsesFirstKnownField(): void
    {
        $http = new MockHttpClient([
            new MockResponse(json_encode(['html' => '<p>x</p>'], JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]);
        $svc = self::client($http, self::requestStackWithSession(), 'https://api.example.com');
        $html = $svc->fetchLibreTemplateHtml('https://api.example.com/api/libre_templates/9');

        self::assertSame('<p>x</p>', $html);
    }

    public function testJwtLoginThenBearerOnCatalog(): void
    {
        $catalogPayload = [
            'hydra:member' => [
                ['@id' => '/api/templates/1', 'name' => 'X'],
            ],
        ];
        $http = new MockHttpClient([
            new MockResponse(json_encode(['token' => 'jwt-test'], JSON_THROW_ON_ERROR), ['http_code' => 200]),
            new MockResponse(json_encode($catalogPayload, JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]);

        $svc = self::client(
            http: $http,
            stack: self::requestStackWithSession(),
            base: 'https://api.example.com',
            email: 'u@example.com',
            password: 'secret',
            notJwt: false,
            isCms: true,
        );
        $items = $svc->fetchLibreTemplateSummaries();

        self::assertSame(2, $http->getRequestsCount());
        self::assertCount(1, $items);
        self::assertSame('X', $items[0]['label']);
    }

    public function testNoSessionReturnsEmptyCatalogWhenCredentialsMissing(): void
    {
        $http = new MockHttpClient([
            new MockResponse(json_encode(['hydra:member' => []], JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]);
        $stack = new RequestStack();
        $svc = self::client(
            $http,
            $stack,
            'https://api.example.com',
            '',
            '',
            '',
            '',
            false,
        );

        self::assertSame([], $svc->fetchLibreTemplateSummaries());
        self::assertSame(0, $http->getRequestsCount());
    }

    public function testCliLoginWithoutSessionFetchesCatalog(): void
    {
        $catalogPayload = [
            'hydra:member' => [
                ['@id' => '/api/templates/1', 'name' => 'Mentions'],
            ],
        ];
        $http = new MockHttpClient([
            new MockResponse(json_encode(['token' => 'cli-jwt'], JSON_THROW_ON_ERROR), ['http_code' => 200]),
            new MockResponse(json_encode($catalogPayload, JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]);
        $stack = new RequestStack();
        $svc = self::client(
            $http,
            $stack,
            'https://api.example.com',
            '',
            '',
            'u@example.com',
            'secret',
            false,
        );
        $items = $svc->fetchLibreTemplateSummaries();

        self::assertCount(1, $items);
        self::assertSame('Mentions', $items[0]['label']);
        self::assertSame(2, $http->getRequestsCount());
    }
}
