<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final class FrontNotFoundRedirectSubscriber implements EventSubscriberInterface
{
    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$event->getThrowable() instanceof NotFoundHttpException) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if ($this->isExcludedPath($path)) {
            return;
        }

        $event->setResponse(new RedirectResponse('/'));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    private function isExcludedPath(string $path): bool
    {
        if ($path === '/' || preg_match('#^/(fr|en)/?$#', $path) === 1) {
            return true;
        }

        return preg_match('#^/(api|_profiler|_wdt)(/|$)#', $path) === 1
            || preg_match('#^/(fr|en)/(admin|login|logout|forgotten_password|re-init-password|reset_password)(/|$)#', $path) === 1;
    }
}
