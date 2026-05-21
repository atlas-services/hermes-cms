<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\FormTemplateKind;
use App\Form\Front\ContactFormType;
use App\Form\Front\LivredorFormType;
use App\Form\Front\NewsletterFormType;
use App\Service\SiteFormSubmissionMailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}', defaults: ['_locale' => 'fr'], requirements: ['_locale' => 'fr|en'])]
final class FrontFormController extends AbstractController
{
    #[Route('/form/contact', name: 'front_form_contact', methods: ['POST'])]
    public function contact(Request $request, SiteFormSubmissionMailer $mailer): Response
    {
        return $this->handleSubmission($request, FormTemplateKind::Contact, ContactFormType::class, $mailer, 'front_contact');
    }

    #[Route('/form/newsletter', name: 'front_form_newsletter', methods: ['POST'])]
    public function newsletter(Request $request, SiteFormSubmissionMailer $mailer): Response
    {
        $redirect = $request->request->getString('_redirect');

        return $this->handleSubmission(
            $request,
            FormTemplateKind::Newsletter,
            NewsletterFormType::class,
            $mailer,
            $redirect !== '' ? $redirect : 'front_home',
        );
    }

    #[Route('/form/livredor', name: 'front_form_livredor', methods: ['POST'])]
    public function livredor(Request $request, SiteFormSubmissionMailer $mailer): Response
    {
        $redirect = $request->request->getString('_redirect');

        return $this->handleSubmission(
            $request,
            FormTemplateKind::Livredor,
            LivredorFormType::class,
            $mailer,
            $redirect !== '' ? $redirect : 'front_home',
        );
    }

    /**
     * @param class-string $formTypeClass
     */
    private function handleSubmission(
        Request $request,
        FormTemplateKind $kind,
        string $formTypeClass,
        SiteFormSubmissionMailer $mailer,
        string $redirectRoute,
    ): Response {
        $locale = $request->getLocale();
        $form = $this->createForm($formTypeClass);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('danger', 'form.flash.invalid');

            return $this->redirect($this->resolveRedirectTarget($request, $redirectRoute, $locale));
        }

        $data = $form->getData();
        if (!\is_array($data)) {
            $this->addFlash('danger', 'form.flash.invalid');

            return $this->redirect($this->resolveRedirectTarget($request, $redirectRoute, $locale));
        }

        $fields = array_map(
            static fn ($v) => $v === null ? null : (string) $v,
            $data,
        );

        try {
            $pageLabel = $request->request->getString('_page_label') ?: null;
            $mailer->send($kind, $fields, $locale, $pageLabel !== '' ? $pageLabel : null);
            $this->addFlash('success', 'form.flash.sent');
        } catch (\Throwable) {
            $this->addFlash('danger', 'form.flash.send_failed');
        }

        return $this->redirect($this->resolveRedirectTarget($request, $redirectRoute, $locale));
    }

    private function resolveRedirectTarget(Request $request, string $route, string $locale): string
    {
        $referer = $request->headers->get('referer');
        if (\is_string($referer) && $referer !== '') {
            return $referer;
        }

        return $this->generateUrl($route, ['_locale' => $locale]);
    }
}
