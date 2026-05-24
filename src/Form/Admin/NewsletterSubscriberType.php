<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class NewsletterSubscriberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class, [
                'label' => 'user.firstname',
                'required' => false,
            ])
            ->add('lastname', TextType::class, [
                'label' => 'user.lastname',
                'required' => false,
            ])
            ->add('email', EmailType::class, [
                'label' => 'user.email',
            ])
            ->add('activeNewsletter', CheckboxType::class, [
                'label' => 'global.active_newsletter',
                'required' => false,
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'user.password_optional',
                'mapped' => false,
                'required' => false,
                'always_empty' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
