<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ResetPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'first_options' => [
                'label' => 'form.label.password',
                'attr' => [
                    'autocomplete' => 'new-password',
                    'class' => 'form-control border border-1 border-dark text-warning-emphasis',
                ],
            ],
            'second_options' => [
                'label' => 'security.reset_password.password_repeat',
                'attr' => [
                    'autocomplete' => 'new-password',
                    'class' => 'form-control border border-1 border-dark text-warning-emphasis',
                ],
            ],
            'invalid_message' => 'security.reset_password.password_mismatch',
            'mapped' => false,
            'constraints' => [
                new NotBlank(message: 'security.reset_password.password_required'),
                new Length(
                    min: 6,
                    max: 4096,
                    minMessage: 'security.reset_password.password_min_length',
                ),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'messages',
        ]);
    }
}
