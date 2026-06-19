<?php

declare(strict_types=1);

namespace App\Form\Front;

use App\Validator\Constraints\FrenchPhoneNumber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ContactFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $inputAttr = ['class' => $options['input_class']];

        $builder
            ->add('firstname', TextType::class, [
                'label' => 'form.contact.firstname',
                'attr' => array_merge($inputAttr, [
                    'autocomplete' => 'given-name',
                    'placeholder' => 'form.contact.firstname_placeholder',
                ]),
                'constraints' => [
                    new Assert\NotBlank(message: 'form.contact.firstname_required'),
                    new Assert\Length(min: 2, max: 80, minMessage: 'form.contact.firstname_min', maxMessage: 'form.contact.firstname_max'),
                ],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'form.contact.lastname',
                'attr' => array_merge($inputAttr, [
                    'autocomplete' => 'family-name',
                    'placeholder' => 'form.contact.lastname_placeholder',
                ]),
                'constraints' => [
                    new Assert\NotBlank(message: 'form.contact.lastname_required'),
                    new Assert\Length(min: 2, max: 80, minMessage: 'form.contact.lastname_min', maxMessage: 'form.contact.lastname_max'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'form.contact.email',
                'attr' => array_merge($inputAttr, [
                    'autocomplete' => 'email',
                    'placeholder' => 'form.contact.email_placeholder',
                ]),
                'constraints' => [
                    new Assert\NotBlank(message: 'form.contact.email_required'),
                    new Assert\Email(message: 'form.contact.email_invalid'),
                ],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'form.contact.telephone',
                'attr' => array_merge($inputAttr, [
                    'autocomplete' => 'tel',
                    'placeholder' => 'form.contact.telephone_placeholder',
                ]),
                'constraints' => [
                    new Assert\NotBlank(message: 'form.contact.telephone_required'),
                    new FrenchPhoneNumber(),
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'form.contact.message',
                'attr' => array_merge($inputAttr, [
                    'rows' => 8,
                    'placeholder' => 'form.contact.message_placeholder',
                ]),
                'constraints' => [
                    new Assert\NotBlank(message: 'form.contact.message_required'),
                    new Assert\Length(min: 10, max: 5000, minMessage: 'form.contact.message_min', maxMessage: 'form.contact.message_max'),
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
