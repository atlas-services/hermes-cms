<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Client HTTP vers l’API Hermes — même logique que ApiClient Hermes 2.2.7
 * (https://github.com/atlas-services/hermes/blob/release/2.2.7/src/Api/ApiClient.php) :
 * login POST sur API_HERMES_BASE_URL + /api/login (corps chiffré libsodium), JWT en session, puis
 * Authorization: Bearer et Content-Type: application/ld+json sur les GET (catalogue + item).
 *
 * Catalogue : API_HERMES_TEMPLATES + API_HERMES_CATALOG_ENTITY (défaut « templates », api-hermes) ; surcharge via $hermesApiLibreCatalogPath.
 *
 * API_HERMES_NOT_JWT_VERSION : fake JWT en session (comportement 2.2.7).
 * API_HERMES_IS_CMS : envoyé dans le JSON de login.
 */
final class HermesApiClient
{
    private const SESSION_JWT_KEY = 'hermes_api_jwt';

    /** Chemin relatif si pas de préfixe API_HERMES_TEMPLATES (api-hermes : /api/templates). */
    private const DEFAULT_CATALOG_RELATIVE_PATH = '/api/templates';

    private const FAKE_JWT_PLACEHOLDER = 'azeaezaeaz';

    /** Comme getEntities(..., $itemsPerPage) en 2.2.7 ; valeur large pour l’admin. */
    private const CATALOG_ITEMS_PER_PAGE = 50;

    /** JWT en mémoire pour les commandes console (sans session HTTP). */
    private ?string $cliBearerToken = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly RequestStack $requestStack,
        private readonly HermesEncryptionService $encryptionService,
        private readonly string $hermesApiBaseUri,
        private readonly string $hermesApiTemplatesUri,
        private readonly string $hermesApiLibreCatalogPath,
        private readonly string $hermesApiEmail,
        private readonly string $hermesApiPassword,
        private readonly bool $hermesApiNotJwtVersion,
        private readonly bool $hermesApiIsCms,
        private readonly string $hermesApiCatalogEntity,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isLibreCatalogConfigured(): bool
    {
        return trim($this->hermesApiBaseUri) !== ''
            || trim($this->hermesApiTemplatesUri) !== ''
            || trim($this->hermesApiLibreCatalogPath) !== '';
    }

    /**
     * Aide au débogage quand le catalogue renvoie une liste vide (sans mot de passe en clair).
     *
     * @return array<string, mixed>
     */
    public function getCatalogDiagnostics(): array
    {
        $session = $this->session();
        $jwt = $session?->get(self::SESSION_JWT_KEY);
        $catalogPath = $this->libreCatalogPathOrDefault();
        $catalogUrl = $this->withItemsPerPage($this->buildUrl($catalogPath));

        $req = $this->diagnosticRequest();

        return [
            'session_available' => $session !== null,
            'jwt_in_session' => \is_string($jwt) && $jwt !== '',
            'jwt_length' => \is_string($jwt) ? \strlen($jwt) : 0,
            'not_jwt_mode' => $this->hermesApiNotJwtVersion,
            'email_configured' => trim($this->hermesApiEmail) !== '',
            'password_configured' => trim($this->hermesApiPassword) !== '',
            'is_hermes_cms_flag' => $this->hermesApiIsCms,
            'catalog_entity' => $this->catalogEntitySegment(),
            'login_url' => $this->loginUrl(),
            'catalog_path_or_override' => $catalogPath,
            'catalog_request_url' => $catalogUrl,
            'api_base_uri' => trim($this->hermesApiBaseUri) !== '' ? $this->hermesApiBaseUri : '(vide)',
            'api_templates_uri' => trim($this->hermesApiTemplatesUri) !== '' ? $this->hermesApiTemplatesUri : '(vide)',
            'last_catalog_http_status' => $req?->attributes->get('hermes_api.catalog_http_status'),
            'last_catalog_error_preview' => $req?->attributes->get('hermes_api.catalog_error_preview'),
            'last_catalog_items_count' => $req?->attributes->get('hermes_api.catalog_items_count'),
            'last_login_http_status' => $req?->attributes->get('hermes_api.login_http_status'),
            'last_login_response_preview' => $req?->attributes->get('hermes_api.login_response_preview'),
            'last_login_exception' => $req?->attributes->get('hermes_api.login_exception'),
            'last_login_token_missing' => $req?->attributes->get('hermes_api.login_token_missing'),
            'ensure_jwt_blocked' => $req?->attributes->get('hermes_api.ensure_jwt_blocked'),
        ];
    }

    /**
     * @return list<array{iri: string, label: string, description: string}>
     */
    public function fetchLibreTemplateSummaries(): array
    {
        if (!$this->isLibreCatalogConfigured()) {
            return [];
        }

        $token = $this->ensureJwt();
        if ($token === null) {
            return [];
        }

        $req = $this->diagnosticRequest();
        try {
            $url = $this->withItemsPerPage($this->buildUrl($this->libreCatalogPathOrDefault()));
            $response = $this->requestAuthenticatedSafe('GET', $url);
            $req?->attributes->set('hermes_api.catalog_http_status', $response->getStatusCode());
            if ($response->getStatusCode() !== 200) {
                $bodyPreview = $this->responseBodyPreview($response);
                $req?->attributes->set('hermes_api.catalog_error_preview', $bodyPreview);
                $this->logger->notice('Hermes API catalogue: statut HTTP {code} — {preview}', [
                    'code' => $response->getStatusCode(),
                    'preview' => $bodyPreview,
                ]);

                return [];
            }
            $data = $response->toArray(false);
            $items = $this->normalizeSummaries($data);
            $req?->attributes->set('hermes_api.catalog_items_count', \count($items));

            return $items;
        } catch (\Throwable $e) {
            $req?->attributes->set('hermes_api.catalog_error_preview', $e->getMessage());
            $this->logger->warning('Hermes API catalogue: {message}', ['message' => $e->getMessage()]);

            return [];
        }
    }

    public function fetchLibreTemplateHtml(string $iri): ?string
    {
        $iri = trim($iri);
        if ($iri === '') {
            return null;
        }

        $token = $this->ensureJwt();
        if ($token === null) {
            return null;
        }

        try {
            $response = $this->requestAuthenticatedSafe('GET', $this->buildUrl($iri));
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            $data = $response->toArray(false);

            return $this->extractHtmlPayload($data);
        } catch (\Throwable $e) {
            $this->logger->warning('Hermes API item: {message}', ['message' => $e->getMessage()]);

            return null;
        }
    }

    private function diagnosticRequest(): ?Request
    {
        return $this->requestStack->getCurrentRequest() ?? $this->requestStack->getMainRequest();
    }

    private function setAuthDiagnostic(string $key, mixed $value): void
    {
        $this->diagnosticRequest()?->attributes->set($key, $value);
    }

    private function removeAuthDiagnostic(string $key): void
    {
        $this->diagnosticRequest()?->attributes->remove($key);
    }

    /** @param array<string, mixed> $data */
    private function loginResponseKeysPreview(array $data): string
    {
        $keys = array_keys($data);
        $preview = json_encode($keys, JSON_THROW_ON_ERROR);
        if (\strlen($preview) > 400) {
            return substr($preview, 0, 400) . '…';
        }

        return $preview;
    }

    private function clearLoginDiagnostics(): void
    {
        $r = $this->diagnosticRequest();
        if ($r === null) {
            return;
        }
        foreach ([
            'hermes_api.login_http_status',
            'hermes_api.login_response_preview',
            'hermes_api.login_exception',
            'hermes_api.login_token_missing',
            'hermes_api.ensure_jwt_blocked',
        ] as $attr) {
            $r->attributes->remove($attr);
        }
    }

    private function session(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest() ?? $this->requestStack->getMainRequest();
        if ($request === null || !$request->hasSession()) {
            return null;
        }

        return $request->getSession();
    }

    private function responseBodyPreview(ResponseInterface $response): string
    {
        try {
            $raw = $response->getContent(false);
        } catch (\Throwable) {
            return '(corps illisible)';
        }
        $raw = trim($raw);
        if ($raw === '') {
            return '(corps vide)';
        }

        return \strlen($raw) > 400 ? substr($raw, 0, 400) . '…' : $raw;
    }

    private function clearJwt(): void
    {
        $this->cliBearerToken = null;
        $this->session()?->remove(self::SESSION_JWT_KEY);
    }

    private function ensureJwt(): ?string
    {
        if (\is_string($this->cliBearerToken) && $this->cliBearerToken !== '') {
            return $this->cliBearerToken;
        }

        $session = $this->session();
        if ($session !== null) {
            $existing = $session->get(self::SESSION_JWT_KEY);
            if (\is_string($existing) && $existing !== '') {
                return $existing;
            }

            return $this->login($session);
        }

        return $this->loginCli();
    }

    private function login(SessionInterface $session): ?string
    {
        $jwt = $this->obtainJwtToken();
        if ($jwt === null) {
            return null;
        }

        $this->removeAuthDiagnostic('hermes_api.ensure_jwt_blocked');
        $session->set(self::SESSION_JWT_KEY, $jwt);

        return $jwt;
    }

    private function loginCli(): ?string
    {
        $jwt = $this->obtainJwtToken();
        if ($jwt === null) {
            return null;
        }

        $this->cliBearerToken = $jwt;
        $this->removeAuthDiagnostic('hermes_api.ensure_jwt_blocked');

        return $jwt;
    }

    private function obtainJwtToken(): ?string
    {
        $this->clearLoginDiagnostics();

        if ($this->hermesApiNotJwtVersion) {
            return self::FAKE_JWT_PLACEHOLDER;
        }

        $email = trim($this->hermesApiEmail);
        $password = trim($this->hermesApiPassword);
        if ($email === '' || $password === '') {
            $this->setAuthDiagnostic('hermes_api.ensure_jwt_blocked', 'missing_credentials');
            $this->logger->notice('Hermes API login : API_HERMES_EMAIL / API_HERMES_PASSWORD vides.');

            return null;
        }

        $loginUrl = $this->loginUrl();
        if ($loginUrl === null) {
            $this->setAuthDiagnostic('hermes_api.ensure_jwt_blocked', 'missing_base_url');
            $this->logger->notice('Hermes API login : API_HERMES_BASE_URL vide (impossible de construire /api/login).');

            return null;
        }

        try {
            $key = random_bytes(\SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
            $encryptedEmail = $this->encryptionService->encrypt($email, $key);
            $encryptedPassword = $this->encryptionService->encrypt($password, $key);
            $json = [
                'email' => $encryptedEmail['encryptedData'],
                'nonceEmail' => $encryptedEmail['nonce'],
                'password' => $encryptedPassword['encryptedData'],
                'noncePassword' => $encryptedPassword['nonce'],
                'key' => base64_encode($key),
                'isHermesCms' => $this->hermesApiIsCms,
            ];

            $response = $this->httpClient->request('POST', $loginUrl, [
                'timeout' => 20,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $json,
            ]);

            $status = $response->getStatusCode();
            $this->setAuthDiagnostic('hermes_api.login_http_status', $status);

            if ($status !== 200) {
                $preview = $this->responseBodyPreview($response);
                $this->setAuthDiagnostic('hermes_api.login_response_preview', $preview);
                $this->setAuthDiagnostic('hermes_api.ensure_jwt_blocked', 'login_http_error');
                $this->logger->notice('Hermes API login : statut HTTP {code} — {preview}', [
                    'code' => $status,
                    'preview' => $preview,
                ]);

                return null;
            }

            $data = $response->toArray(false);
            $jwt = $data['token'] ?? null;
            if (!\is_string($jwt) || $jwt === '') {
                $this->setAuthDiagnostic('hermes_api.login_token_missing', true);
                $this->setAuthDiagnostic('hermes_api.login_response_preview', $this->loginResponseKeysPreview($data));
                $this->setAuthDiagnostic('hermes_api.ensure_jwt_blocked', 'login_no_token');
                $this->logger->notice('Hermes API login : réponse 200 sans champ « token » valide.');

                return null;
            }

            $this->setAuthDiagnostic('hermes_api.login_token_missing', false);
            $this->removeAuthDiagnostic('hermes_api.login_response_preview');

            return $jwt;
        } catch (\Throwable $e) {
            $this->setAuthDiagnostic('hermes_api.login_exception', $e->getMessage());
            $this->setAuthDiagnostic('hermes_api.ensure_jwt_blocked', 'login_exception');
            $this->logger->warning('Hermes API login : {message}', ['message' => $e->getMessage()]);

            return null;
        }
    }

    private function loginUrl(): ?string
    {
        $base = trim($this->hermesApiBaseUri);
        if ($base === '') {
            $base = $this->effectiveBaseForRelativePaths();
        }
        if ($base === '') {
            return null;
        }

        return rtrim($base, '/') . '/api/login';
    }

    private function authorizedRequestOptions(): array
    {
        $bearer = '';
        if (\is_string($this->cliBearerToken) && $this->cliBearerToken !== '') {
            $bearer = $this->cliBearerToken;
        } else {
            $token = $this->session()?->get(self::SESSION_JWT_KEY);
            $bearer = \is_string($token) && $token !== '' ? $token : '';
        }

        return [
            'timeout' => 20,
            'headers' => [
                'Accept' => 'application/ld+json, application/json',
                'Content-Type' => 'application/ld+json',
                'Authorization' => 'Bearer ' . $bearer,
            ],
        ];
    }

    private function requestAuthenticatedSafe(string $method, string $url): ResponseInterface
    {
        $session = $this->session();
        $response = $this->httpClient->request($method, $url, $this->authorizedRequestOptions());
        if ($response->getStatusCode() !== 401) {
            return $response;
        }
        $this->clearJwt();
        if ($session !== null) {
            if ($this->login($session) !== null) {
                return $this->httpClient->request($method, $url, $this->authorizedRequestOptions());
            }
        } elseif ($this->loginCli() !== null) {
            return $this->httpClient->request($method, $url, $this->authorizedRequestOptions());
        }

        return $response;
    }

    private function buildUrl(string $pathOrUrl): string
    {
        if (str_starts_with($pathOrUrl, 'http://') || str_starts_with($pathOrUrl, 'https://')) {
            return $pathOrUrl;
        }

        $base = $this->effectiveBaseForRelativePaths();
        if ($base === '') {
            return $pathOrUrl;
        }

        return rtrim($base, '/') . '/' . ltrim($pathOrUrl, '/');
    }

    private function effectiveBaseForRelativePaths(): string
    {
        $b = trim($this->hermesApiBaseUri);
        if ($b !== '') {
            return rtrim($b, '/');
        }

        $t = trim($this->hermesApiTemplatesUri);
        if ($t === '' || !str_starts_with($t, 'http')) {
            return '';
        }

        $parts = parse_url($t);
        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        $host = $parts['host'];
        if (isset($parts['port'])) {
            $host .= ':' . $parts['port'];
        }

        return $parts['scheme'] . '://' . $host;
    }

    private function libreCatalogPathOrDefault(): string
    {
        $override = trim($this->hermesApiLibreCatalogPath);
        if ($override !== '') {
            return $override;
        }

        $templates = trim($this->hermesApiTemplatesUri);
        if ($templates !== '') {
            return rtrim($templates, '/') . '/' . $this->catalogEntitySegment();
        }

        return self::DEFAULT_CATALOG_RELATIVE_PATH;
    }

    private function catalogEntitySegment(): string
    {
        $s = trim($this->hermesApiCatalogEntity);

        return $s !== '' ? $s : 'templates';
    }

    private function withItemsPerPage(string $url): string
    {
        $sep = str_contains($url, '?') ? '&' : '?';

        return $url . $sep . 'itemsPerPage=' . self::CATALOG_ITEMS_PER_PAGE;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array{iri: string, label: string, description: string}>
     */
    private function normalizeSummaries(array $data): array
    {
        $members = [];
        if (isset($data['hydra:member']) && \is_array($data['hydra:member'])) {
            $members = $data['hydra:member'];
        } elseif (isset($data['member']) && \is_array($data['member'])) {
            $members = $data['member'];
        } elseif (isset($data['@graph']) && \is_array($data['@graph'])) {
            $members = $data['@graph'];
        } elseif (array_is_list($data)) {
            $members = $data;
        }

        $out = [];
        foreach ($members as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $iri = null;
            if (isset($row['@id']) && \is_string($row['@id'])) {
                $iri = $row['@id'];
            } elseif (isset($row['id']) && \is_string($row['id'])) {
                $iri = $row['id'];
            } elseif (isset($row['url']) && \is_string($row['url'])) {
                $iri = $row['url'];
            }
            if ($iri === null || $iri === '') {
                continue;
            }
            if (!str_starts_with($iri, 'http')) {
                $iri = $this->buildUrl($iri);
            }
            $label = '—';
            foreach (['name', 'title', 'label', 'code', 'slug'] as $k) {
                if (isset($row[$k]) && \is_string($row[$k]) && $row[$k] !== '') {
                    $label = $row[$k];
                    break;
                }
            }
            $description = '';
            foreach (['description', 'summary', 'content'] as $k) {
                if (isset($row[$k]) && \is_string($row[$k])) {
                    $description = $row[$k];
                    break;
                }
            }

            $out[] = [
                'iri' => $iri,
                'label' => $label,
                'description' => $description,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractHtmlPayload(array $data): ?string
    {
        foreach (['html', 'templateHtml', 'snippet', 'body', 'content', 'markup'] as $key) {
            if (isset($data[$key]) && \is_string($data[$key]) && trim($data[$key]) !== '') {
                return $data[$key];
            }
        }

        return null;
    }
}
