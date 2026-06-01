<?php

declare(strict_types=1);

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ContentTransferType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('operation', ChoiceType::class, [
                'label' => 'admin.content_transfer.operation',
                'choices' => [
                    'admin.content_transfer.copy' => 'copy',
                    'admin.content_transfer.move' => 'move',
                ],
                'constraints' => [new NotBlank()],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'admin.content_transfer.submit',
                'attr' => ['class' => 'btn btn-success'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'show_section_target' => false,
        ]);
        $resolver->setAllowedTypes('show_section_target', 'bool');
    }
}
