<?php

declare(strict_types=1);

namespace App\Form\Front;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class NewsletterFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $inputAttr = ['class' => $options['input_class']];

        $builder
            ->add('firstname', TextType::class, [
                'label' => 'form.newsletter.firstname',
                'attr' => array_merge($inputAttr, ['autocomplete' => 'given-name']),
                'constraints' => [
                    new Assert\NotBlank(message: 'form.newsletter.firstname_required'),
                    new Assert\Length(min: 2, max: 80),
                ],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'form.newsletter.lastname',
                'attr' => array_merge($inputAttr, ['autocomplete' => 'family-name']),
                'constraints' => [
                    new Assert\NotBlank(message: 'form.newsletter.lastname_required'),
                    new Assert\Length(min: 2, max: 80),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'form.newsletter.email',
                'attr' => array_merge($inputAttr, ['autocomplete' => 'email']),
                'constraints' => [
                    new Assert\NotBlank(message: 'form.newsletter.email_required'),
                    new Assert\Email(message: 'form.newsletter.email_invalid'),
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
