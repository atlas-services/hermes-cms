<?php

declare(strict_types=1);

namespace App\Form\Front;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
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

        $this->addSpamFields($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'messages',
            'input_class' => 'form-control',
            'csrf_protection' => false,
            'spam_honeypot_name' => null,
            'spam_time_name' => null,
            'spam_token_name' => null,
            'spam_started_at' => null,
            'spam_token' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function addSpamFields(FormBuilderInterface $builder, array $options): void
    {
        if (!\is_string($options['spam_honeypot_name'])) {
            return;
        }

        $builder
            ->add($options['spam_honeypot_name'], TextType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'row_attr' => [
                    'aria-hidden' => 'true',
                    'style' => 'position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;',
                ],
                'attr' => [
                    'tabindex' => '-1',
                    'autocomplete' => 'off',
                ],
            ])
            ->add((string) $options['spam_time_name'], HiddenType::class, [
                'mapped' => false,
                'data' => (string) $options['spam_started_at'],
            ])
            ->add((string) $options['spam_token_name'], HiddenType::class, [
                'mapped' => false,
                'data' => (string) $options['spam_token'],
            ]);
    }
}
