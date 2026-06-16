<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Services\Booking\HermesBookingStatus;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Empêche l'accès aux routes du bundle si le module n'est pas pleinement opérationnel
 * (bundle absent, env désactivé ou tables non migrées).
 *
 * Détection par chemin URL (pas seulement _route) : le routeur Symfony n'a pas encore
 * tourné à la priorité 32, où ce listener était enregistré initialement.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 16)]
final class HermesBookingRouteGuardSubscriber
{
    public function __construct(
        private readonly HermesBookingStatus $bookingStatus,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->isBookingRequest($event->getRequest())) {
            return;
        }

        if (!$this->bookingStatus->isActive()) {
            throw new NotFoundHttpException('Booking module is not available.');
        }
    }

    private function isBookingRequest(Request $request): bool
    {
        $route = $request->attributes->get('_route');
        if (\is_string($route) && str_starts_with($route, 'hermes_booking_')) {
            return true;
        }

        $path = $request->getPathInfo();

        return str_starts_with($path, '/admin/booking')
            || preg_match('#^/[a-z]{2,3}/booking(/|$)#', $path) === 1;
    }
}
