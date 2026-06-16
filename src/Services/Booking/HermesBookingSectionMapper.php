<?php

declare(strict_types=1);

namespace App\Services\Booking;

use Twig\Attribute\AsTwigFunction;

/**
 * Mappe une section Hermes vers la clé / libellé attendus par HermesBookingBundle.
 *
 * Convention Hermes : booking_key = s{sectionId} (ex. s120), label = nom du menu porteur.
 */
final class HermesBookingSectionMapper
{
    public static function bookingKeyFromSectionId(int $sectionId): string
    {
        return 's'.$sectionId;
    }

    public static function sectionIdFromBookingKey(string $bookingKey): ?int
    {
        if (preg_match('/^s(\d+)$/', $bookingKey, $matches) !== 1) {
            return null;
        }

        $id = (int) $matches[1];

        return $id > 0 ? $id : null;
    }

    /**
     * @return array{booking_key: string, booking_label: string, section_id: int}
     */
    public function resolve(object $section): array
    {
        $sectionId = method_exists($section, 'getId') ? (int) $section->getId() : (int) ($section->id ?? 0);
        if ($sectionId <= 0) {
            return ['booking_key' => '', 'booking_label' => '', 'section_id' => 0];
        }

        $label = sprintf('Section #%d', $sectionId);
        if (method_exists($section, 'getMenu')) {
            $menu = $section->getMenu();
            if ($menu !== null && method_exists($menu, 'getName')) {
                $name = trim((string) $menu->getName());
                if ($name !== '') {
                    $label = $name;
                }
            }
        }

        return [
            'booking_key' => self::bookingKeyFromSectionId($sectionId),
            'booking_label' => $label,
            'section_id' => $sectionId,
        ];
    }

    #[AsTwigFunction('hermes_booking_key')]
    public function twigBookingKey(object $section): string
    {
        return $this->resolve($section)['booking_key'];
    }
}
