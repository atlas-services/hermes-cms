<?php

declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\Services\Booking\HermesBookingConfigDefaults;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class MergeHermesBookingConfigsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $bookingDefaults = HermesBookingConfigDefaults::load();
        if ($bookingDefaults === []) {
            return;
        }

        if (!$container->hasParameter('configs')) {
            return;
        }

        /** @var array<string, mixed> $configs */
        $configs = $container->getParameter('configs');
        $configs = array_merge($configs, $bookingDefaults);
        $container->setParameter('configs', $configs);
    }
}
