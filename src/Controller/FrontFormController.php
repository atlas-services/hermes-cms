<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\FormTemplateKind;
use App\Form\Front\ContactFormType;
use App\Form\Front\LivredorFormType;
use App\Form\Front\NewsletterFormType;
use App\Service\FrontFormSubmissionHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}', defaults: ['_locale' => 'fr'], requirements: ['_locale' => 'fr|en'], priority: 10)]
final class FrontFormController extends AbstractController
{
    #[Route('/form/contact', name: 'front_form_contact', methods: ['POST'])]
    public function contact(Request $request, FrontFormSubmissionHandler $handler): Response
    {
        return $handler->handle($request, FormTemplateKind::Contact, ContactFormType::class, 'front_contact')
            ?? $this->redirectToRoute('front_contact', ['_locale' => $request->getLocale()]);
    }

    #[Route('/form/newsletter', name: 'front_form_newsletter', methods: ['POST'])]
    public function newsletter(Request $request, FrontFormSubmissionHandler $handler): Response
    {
        $redirect = $request->request->getString('_redirect');

        return $handler->handle(
            $request,
            FormTemplateKind::Newsletter,
            NewsletterFormType::class,
            $redirect !== '' ? $redirect : 'front_home',
        ) ?? $this->redirectToRoute('front_home', ['_locale' => $request->getLocale()]);
    }

    #[Route('/form/livredor', name: 'front_form_livredor', methods: ['POST'])]
    public function livredor(Request $request, FrontFormSubmissionHandler $handler): Response
    {
        $redirect = $request->request->getString('_redirect');

        return $handler->handle(
            $request,
            FormTemplateKind::Livredor,
            LivredorFormType::class,
            $redirect !== '' ? $redirect : 'front_home',
        ) ?? $this->redirectToRoute('front_home', ['_locale' => $request->getLocale()]);
    }
}
