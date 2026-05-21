<?php

declare(strict_types=1);

namespace App\Form\Front;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class LivredorFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $inputAttr = ['class' => $options['input_class']];

        $builder
            ->add('lastname', TextType::class, [
                'label' => 'form.livredor.lastname',
                'attr' => array_merge($inputAttr, ['autocomplete' => 'family-name']),
                'constraints' => [
                    new Assert\NotBlank(message: 'form.livredor.lastname_required'),
                    new Assert\Length(min: 2, max: 80),
                ],
            ])
            ->add('firstname', TextType::class, [
                'label' => 'form.livredor.firstname',
                'attr' => array_merge($inputAttr, ['autocomplete' => 'given-name']),
                'constraints' => [
                    new Assert\NotBlank(message: 'form.livredor.firstname_required'),
                    new Assert\Length(min: 2, max: 80),
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'form.livredor.message',
                'attr' => array_merge($inputAttr, ['rows' => 6]),
                'constraints' => [
                    new Assert\NotBlank(message: 'form.livredor.message_required'),
                    new Assert\Length(min: 10, max: 5000),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'messages',
            'input_class' => 'form-control',
        ]);
    }
}
