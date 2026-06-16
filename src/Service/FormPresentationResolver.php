<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\FormTemplateKind;

/**
 * Styles des formulaires front (configs type contact / newsletter / livredor, Hermes 2.2.7).
 *
 * @phpstan-type FormPresentation array{
 *   bgcolor: string,
 *   color: string,
 *   bgcolor_btn: string,
 *   bgcolor_input: string,
 *   color_input: string,
 *   border_color_input: string,
 *   rounded_input: string,
 *   py_input: string,
 *   my_input: string,
 *   presentation: ?string,
 * }
 */
final class FormPresentationResolver
{
    /**
     * @param array<string, mixed> $configs
     *
     * @return FormPresentation
     */
    public function resolve(FormTemplateKind $kind, array $configs): array
    {
        $prefix = match ($kind) {
            FormTemplateKind::Contact => 'contact',
            FormTemplateKind::Newsletter => 'newsletter',
            FormTemplateKind::Livredor => 'livredor',
            FormTemplateKind::Booking => 'booking',
        };

        $rounded = (int) ($configs[$prefix.'_rounded_input'] ?? $configs['contact_rounded_input'] ?? 0);
        $py = (int) ($configs[$prefix.'_py_input'] ?? $configs['contact_py_input'] ?? 2);
        $my = (int) ($configs[$prefix.'_my_input'] ?? $configs['contact_my_input'] ?? 2);

        return [
            'bgcolor' => (string) ($configs[$prefix . '_bgcolor'] ?? $configs['contact_bgcolor'] ?? 'transparent'),
            'color' => (string) ($configs[$prefix . '_color'] ?? $configs['contact_color'] ?? '#000000'),
            'bgcolor_btn' => (string) ($configs[$prefix . '_bgcolor_btn'] ?? $configs['contact_bgcolor_btn'] ?? 'btn-outline-primary'),
            'bgcolor_input' => (string) ($configs[$prefix.'_bgcolor_input'] ?? $configs['contact_bgcolor_input'] ?? '#ffffff'),
            'color_input' => (string) ($configs[$prefix.'_color_input'] ?? $configs['contact_color_input'] ?? '#000000'),
            'border_color_input' => (string) ($configs[$prefix.'_border_color_input'] ?? $configs['contact_border_color_input'] ?? '#dee2e6'),
            'rounded_input' => (string) max(0, min(5, $rounded)),
            'py_input' => (string) max(0, min(5, $py)),
            'my_input' => (string) max(0, min(5, $my)),
            'presentation' => $this->nullableString($configs[$prefix . '_presentation'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $configs
     */
    public function contactEmailDisplay(array $configs): ?string
    {
        $affiche = $configs['contact_affiche'] ?? false;
        if (!$this->isConfigFlagEnabled($affiche)) {
            return null;
        }

        $email = $this->nullableString($configs['contact'] ?? null);

        return $email !== '' ? $email : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);

        return $s === '' || $s === '~' ? null : $s;
    }

    private function isConfigFlagEnabled(mixed $value): bool
    {
        if ($value === false || $value === null) {
            return false;
        }

        $s = strtolower(trim((string) $value));

        return $s !== '' && $s !== '0' && $s !== 'false' && $s !== 'no';
    }
}
