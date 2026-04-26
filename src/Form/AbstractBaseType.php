<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractBaseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('active', 'Symfony\Component\Form\Extension\Core\Type\CheckboxType', [
                'required' => false,
                'label' => false,
                'label_attr' => ['class' => 'switch-custom'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'name_required'=> false,
            'name_constraints'=> [],
        ]);
    }
}
