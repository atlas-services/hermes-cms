<?php

namespace App\Form;

use App\Entity\Menu;
use App\Service\MenuManager;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MenuType extends AbstractType
{
    public function __construct(
        private readonly MenuManager $menuManager,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('active', CheckboxType::class, [
                'required' => false,
                'label' => 'form.label.active',
                'attr' => [
                    'class' => 'form-check-input ',
                ],
                'label_attr' => $options['label_attr']
                ])
            ->add('name', null ,  [
                'label' => 'form.label.name',
                'label_attr' => $options['label_attr'],
                ])
            ->add('parent', EntityType::class, [
                'required' => false,
                'label' => 'form.label.parent',
                'class' => Menu::class,
                'choice_label' => 'name',
                'placeholder' => 'form.label.parent_menu',
                'autocomplete' => true,

                'row_attr' => [
                    'class' => 'row mb-3 align-items-center',
                ],

                'label_attr' => [
                    'class' => 'col-sm-2 col-form-label text-warning-emphasis',
                ],

                'attr' => [
                    'class' => 'form-select',
                ],
            ])
        ;

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $menu = $event->getData();
            if (!$menu instanceof Menu) {
                return;
            }
            $this->menuManager->assignUniqueReferenceName($menu);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Menu::class,
            'label_attr' => [
                'class' => 'col-form-label col-sm-2 text-warning-emphasis ',
            ],
            'attr_select' => [
                'class' => 'form-select col-12 col-sm-6',
            ],
        ]);
    }
}
