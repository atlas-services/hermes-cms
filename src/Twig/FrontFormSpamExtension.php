<?php

declare(strict_types=1);

namespace App\Twig;

use App\Enum\FormTemplateKind;
use App\Service\FrontFormSpamProtection;
use Twig\Attribute\AsTwigFunction;

final class FrontFormSpamExtension
{
    public function __construct(
        private readonly FrontFormSpamProtection $spamProtection,
    ) {
    }

    /**
     * @return array{honeypot_name: string, time_name: string, token_name: string, started_at: string, token: string}
     */
    #[AsTwigFunction('front_form_spam_fields')]
    public function fields(string $kind): array
    {
        return $this->spamProtection->fields(FormTemplateKind::from($kind));
    }
}
