<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\FormTemplateKind;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Traitement POST des formulaires front (contact, newsletter, livre d’or).
 */
final class FrontFormSubmissionHandler
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly SiteFormSubmissionMailer $mailer,
        private readonly FrontFormDraftStorage $draftStorage,
        private readonly LoggerInterface $logger,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param class-string $formTypeClass
     * @param array<string, mixed> $formOptions
     */
    public function handle(
        Request $request,
        FormTemplateKind $kind,
        string $formTypeClass,
        string $fallbackRoute,
        array $formOptions = [],
    ): ?Response {
        if (!$request->isMethod('POST')) {
            return null;
        }

        $locale = $request->getLocale();
        $form = $this->formFactory->create($formTypeClass, null, $formOptions);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $formName = $form->getName();
            $posted = $request->request->all($formName);
            if (\is_array($posted) && $posted !== []) {
                $this->draftStorage->save($kind, $posted);
            }

            if ($form->isSubmitted()) {
                $this->logger->warning('Front form validation failed.', [
                    'kind' => $kind->value,
                    'errors' => (string) $form->getErrors(true, false),
                ]);
            } else {
                $this->logger->warning('Front form not submitted.', [
                    'kind' => $kind->value,
                    'request_keys' => array_keys($request->request->all()),
                ]);
            }

            $this->addFlash($request, 'danger', 'form.flash.invalid');

            return new RedirectResponse($this->resolveRedirectTarget($request, $fallbackRoute, $locale));
        }

        $data = $form->getData();
        if (!\is_array($data)) {
            $this->logger->error('Front form data is not an array.', [
                'kind' => $kind->value,
                'type' => \get_debug_type($data),
            ]);
            $this->addFlash($request, 'danger', 'form.flash.invalid');

            return new RedirectResponse($this->resolveRedirectTarget($request, $fallbackRoute, $locale));
        }

        $fields = array_map(
            static fn ($v) => $v === null ? null : (string) $v,
            $data,
        );

        try {
            $pageLabel = $request->request->getString('_page_label') ?: null;
            $this->mailer->send($kind, $fields, $locale, $pageLabel !== '' ? $pageLabel : null);
            $this->addFlash($request, 'success', 'form.flash.sent');
            $this->logger->info('Front form mail sent.', [
                'kind' => $kind->value,
                'template' => $kind->mailTemplate(),
                'recipient' => 'resolved-by-mailer',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Front form mail send failed.', [
                'kind' => $kind->value,
                'template' => $kind->mailTemplate(),
                'exception' => $e,
            ]);
            $this->addFlash($request, 'danger', 'form.flash.send_failed');
        }

        return new RedirectResponse($this->resolveRedirectTarget($request, $fallbackRoute, $locale));
    }

    private function addFlash(Request $request, string $type, string $messageKey): void
    {
        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }
        if (!$session instanceof FlashBagAwareSessionInterface) {
            return;
        }

        $session->getFlashBag()->add($type, $messageKey);
    }

    private function resolveRedirectTarget(Request $request, string $route, string $locale): string
    {
        $redirect = $request->request->getString('_redirect');
        if ($redirect !== '') {
            return $redirect;
        }

        $referer = $request->headers->get('referer');
        if (\is_string($referer) && $referer !== '') {
            return $referer;
        }

        if (str_starts_with($route, '/')) {
            return $route;
        }

        return $this->urlGenerator->generate($route, ['_locale' => $locale]);
    }
}
