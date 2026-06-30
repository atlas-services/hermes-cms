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
 *   color_btn: ?string,
 *   button_bgcolor: ?string,
 *   reservation_form_bgcolor: ?string,
 *   bgcolor_input: string,
 *   color_input: string,
 *   border_color_input: string,
 *   rounded_input: string,
 *   rounded_message?: string,
 *   py_input: string,
 *   my_input: string,
 *   width_firstname?: string,
 *   width_lastname?: string,
 *   width_email?: string,
 *   width_telephone?: string,
 *   width_message?: string,
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

        $presentation = [
            'bgcolor' => (string) ($configs[$prefix . '_bgcolor'] ?? $configs['contact_bgcolor'] ?? 'transparent'),
            'color' => (string) ($configs[$prefix . '_color'] ?? $configs['contact_color'] ?? '#000000'),
            'bgcolor_btn' => (string) ($configs[$prefix . '_bgcolor_btn'] ?? $configs['contact_bgcolor_btn'] ?? 'btn-outline-primary'),
            'color_btn' => $this->nullableColor($configs[$prefix . '_color_btn'] ?? null),
            'reservation_form_bgcolor' => $this->nullableColor($configs[$prefix . '_reservation_form'] ?? null),
            'bgcolor_input' => (string) ($configs[$prefix.'_bgcolor_input'] ?? $configs['contact_bgcolor_input'] ?? '#ffffff'),
            'color_input' => (string) ($configs[$prefix.'_color_input'] ?? $configs['contact_color_input'] ?? '#000000'),
            'border_color_input' => (string) ($configs[$prefix.'_border_color_input'] ?? $configs['contact_border_color_input'] ?? '#dee2e6'),
            'rounded_input' => (string) max(0, min(5, $rounded)),
            'py_input' => (string) max(0, min(5, $py)),
            'my_input' => (string) max(0, min(5, $my)),
        ];

        if ($kind === FormTemplateKind::Contact) {
            $presentation['width_firstname'] = $this->resolveBootstrapCol($configs, 'contact_width_firstname', 6);
            $presentation['width_lastname'] = $this->resolveBootstrapCol($configs, 'contact_width_lastname', 6);
            $presentation['width_email'] = $this->resolveBootstrapCol($configs, 'contact_width_email', 6);
            $presentation['width_telephone'] = $this->resolveBootstrapCol($configs, 'contact_width_telephone', 6);
            $presentation['width_message'] = $this->resolveBootstrapCol($configs, 'contact_width_message', 12);
            $presentation['rounded_message'] = $this->resolveRounded(
                $configs,
                'contact_rounded_message',
                (int) $presentation['rounded_input'],
            );
        }

        return $presentation;
    }

    /**
     * @param array<string, mixed> $configs
     */
    private function resolveBootstrapCol(array $configs, string $code, int $default): string
    {
        $value = (int) ($configs[$code] ?? $default);

        return (string) max(1, min(12, $value));
    }

    /**
     * @param array<string, mixed> $configs
     */
    private function resolveRounded(array $configs, string $code, int $default): string
    {
        $value = (int) ($configs[$code] ?? $default);

        return (string) max(0, min(5, $value));
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);

        return $s === '' || $s === '~' ? null : $s;
    }

    private function nullableColor(mixed $value): ?string
    {
        $s = $this->nullableString($value);

        return $s !== null && preg_match('/^#[0-9a-fA-F]{3,8}$/', $s) === 1 ? $s : null;
    }
}
