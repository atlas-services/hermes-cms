<?php

declare(strict_types=1);

if (!class_exists(\AtlasServices\HermesBookingBundle\HermesBookingBundle::class)) {
    return [];
}

return [
    'hermes_booking' => [
        'timezone' => 'Europe/Paris',
        'admin_email' => '%hermes_admin_email%',
        'from_email' => '%hermes_admin_email%',
        'section_resolver' => [
            'entity' => 'App\Entity\Section',
        ],
    ],
];
