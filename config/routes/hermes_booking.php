<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    if (!class_exists(\AtlasServices\HermesBookingBundle\HermesBookingBundle::class)) {
        return;
    }

    $routesFile = dirname(__DIR__, 2).'/vendor/atlas-services/hermes-booking-bundle/config/routes.yaml';
    if (!is_file($routesFile)) {
        return;
    }

    $routes->import($routesFile);
};
