<?php

declare(strict_types=1);

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class LocaleCopyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $targetLocales = array_values(array_filter(
            $options['locales'],
            static fn (string $locale): bool => $locale !== $options['source_locale'],
        ));

        $builder
            ->add('locale', ChoiceType::class, [
                'label' => 'global.locale',
                'placeholder' => 'global.locale',
                'choices' => array_combine($targetLocales, $targetLocales),
                'constraints' => [new NotBlank()],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'global.update',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'locales' => ['fr', 'en'],
            'source_locale' => 'fr',
        ]);
        $resolver->setAllowedTypes('locales', 'array');
        $resolver->setAllowedTypes('source_locale', 'string');
    }
}
