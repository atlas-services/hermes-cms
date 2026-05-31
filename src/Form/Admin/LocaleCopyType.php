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
        $builder
            ->add('locale', ChoiceType::class, [
                'label' => 'global.locale',
                'placeholder' => 'global.locale',
                'choices' => $options['locale_choices'],
                'constraints' => [new NotBlank()],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'global.update',
                'attr' => ['class' => 'btn btn-success'],
                'row_attr' => ['class' => 'mt-4 mb-0'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'locale_choices' => [],
            'source_locale' => 'fr',
        ]);
        $resolver->setAllowedTypes('locale_choices', 'array');
        $resolver->setAllowedTypes('source_locale', 'string');
    }
}
