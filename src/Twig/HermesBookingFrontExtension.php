<?php

declare(strict_types=1);

namespace App\Twig;

use App\Enum\FormTemplateKind;
use App\Service\ConfigGlobalsProvider;
use App\Service\FormPresentationResolver;
use App\Services\Booking\HermesBookingSectionMapper;
use App\Services\Booking\HermesBookingStatus;
use AtlasServices\HermesBookingBundle\Service\BookingAvailabilityService;
use AtlasServices\HermesBookingBundle\Service\BookingFormVarsProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * Adaptateur Hermes : une section CMS → un seul contexte Twig pour le formulaire booking.
 */
final class HermesBookingFrontExtension
{
    public function __construct(
        private readonly FormPresentationResolver $presentationResolver,
        private readonly ConfigGlobalsProvider $configGlobalsProvider,
        private readonly RequestStack $requestStack,
        private readonly HermesBookingSectionMapper $sectionMapper,
        private readonly HermesBookingStatus $bookingStatus,
        private readonly ?BookingFormVarsProvider $bookingFormVarsProvider = null,
        private readonly ?BookingAvailabilityService $bookingAvailabilityService = null,
    ) {
    }

    /**
     * Contexte unique pour le front booking (clé, libellé, formulaire, URLs, styles).
     *
     * @return array<string, mixed>
     */
    #[AsTwigFunction('hermes_booking_context')]
    public function bookingContext(object $section): array
    {
        if (!$this->bookingStatus->isActive() || $this->bookingFormVarsProvider === null) {
            return [];
        }

        $mapped = $this->sectionMapper->resolve($section);
        if ($mapped['booking_key'] === '') {
            return [];
        }

        if ($this->bookingAvailabilityService !== null) {
            $this->bookingAvailabilityService->getCalendarForKey(
                $mapped['booking_key'],
                $mapped['booking_label'],
            );
        }

        $configs = $this->configGlobalsProvider->getConfigs();
        $resolved = $this->presentationResolver->resolve(FormTemplateKind::Booking, $configs);
        $presentation = [
            'bgcolor' => $resolved['bgcolor'],
            'color' => $resolved['color'],
            'bgcolor_btn' => $resolved['bgcolor_btn'],
            'color_btn' => $resolved['color_btn'],
            'button_bgcolor' => $resolved['button_bgcolor'],
            'bgcolor_input' => $resolved['bgcolor_input'],
            'color_input' => $resolved['color_input'],
            'border_color_input' => $resolved['border_color_input'],
            'rounded_input' => $resolved['rounded_input'],
            'py_input' => $resolved['py_input'],
            'my_input' => $resolved['my_input'],
        ];

        $presentationText = $this->nullableString($configs['booking_presentation'] ?? $resolved['presentation'] ?? null);
        $userText = $this->nullableString($configs['booking_user'] ?? null);
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';

        $vars = $this->bookingFormVarsProvider->provide(
            $mapped['booking_key'],
            $locale,
            $presentation,
            $presentationText,
            $userText,
        );

        if ($vars === []) {
            return [];
        }

        $vars['booking_label'] = $mapped['booking_label'];
        $vars['section_id'] = $mapped['section_id'];
        $vars['form_id'] = 'booking-'.$mapped['booking_key'];

        return $vars;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);

        return $s === '' || $s === '~' ? null : $s;
    }
}
