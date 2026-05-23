<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\FormTemplateKind;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Conserve les données saisies après une validation échouée (affichage des erreurs au rechargement).
 */
final class FrontFormDraftStorage
{
    private const SESSION_PREFIX = 'hermes_form_draft_';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(FormTemplateKind $kind, array $data): void
    {
        $session = $this->requestStack->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }
        $session->set(self::SESSION_PREFIX.$kind->value, $data);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function consume(FormTemplateKind $kind): ?array
    {
        $session = $this->requestStack->getSession();
        $key = self::SESSION_PREFIX.$kind->value;
        if (!$session->has($key)) {
            return null;
        }

        $data = $session->get($key);
        $session->remove($key);

        return \is_array($data) ? $data : null;
    }
}
