<?php

declare(strict_types=1);

namespace App\Twig;

use App\Enum\FormTemplateKind;
use App\Form\Front\ContactFormType;
use App\Form\Front\LivredorFormType;
use App\Form\Front\NewsletterFormType;
use App\Service\FormPresentationResolver;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Attribute\AsTwigFunction;

final class FrontFormExtension
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly FormPresentationResolver $presentationResolver,
    ) {
    }

    /**
     * Variables Twig pour les gabarits front/section/{contact,newsletter,livredor}.html.twig.
     *
     * @param array<string, mixed> $configs
     *
     * @return array<string, mixed>
     */
    #[AsTwigFunction('hermes_formulaire_vars')]
    public function formulaireVars(?string $templateCode, array $configs): array
    {
        $kind = FormTemplateKind::tryFromTemplateCode($templateCode);
        if ($kind === null) {
            return [];
        }

        $presentation = $this->presentationResolver->resolve($kind, $configs);
        $inputClass = sprintf(
            'form-control hermes-form-field rounded-%s py-%s my-%s',
            $presentation['rounded_input'],
            $presentation['py_input'],
            $presentation['my_input'],
        );

        return match ($kind) {
            FormTemplateKind::Contact => [
                'contact_form' => $this->formFactory->create(ContactFormType::class, null, [
                    'input_class' => $inputClass,
                ])->createView(),
                'form_presentation' => $presentation,
                'contact_email_display' => $this->presentationResolver->contactEmailDisplay($configs),
            ],
            FormTemplateKind::Newsletter => [
                'newsletter_form' => $this->formFactory->create(NewsletterFormType::class, null, [
                    'input_class' => $inputClass,
                ])->createView(),
                'form_presentation' => $presentation,
            ],
            FormTemplateKind::Livredor => [
                'livredor_form' => $this->formFactory->create(LivredorFormType::class, null, [
                    'input_class' => $inputClass,
                ])->createView(),
                'form_presentation' => $presentation,
            ],
        };
    }
}
