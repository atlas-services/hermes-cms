<?php

namespace App\Form;

use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use App\Form\AbstractNameBaseType;
use App\Form\CKEditor5Type;
use App\Repository\TemplateRepository;
use App\Service\MenuManager;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PostType extends AbstractNameBaseType
{
    public function __construct(private TemplateRepository $templateRepository, private MenuManager $menuManager)
    {

    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $options['label_name']= 'global.name';
        $options['tooltip']= 'Nom';
        $options['active'] = false;
        $options['name'] = false;
        parent::buildForm($builder, $options);
        $builder
            ->add('name', null, [
                'label' => 'form.label.name'
            ])
            ->add('template', EntityType::class, [
                'class'=> Template::class,
                'mapped' => false,
                'required' => false, // important (héritage possible)
                'label' => 'form.label.post_template',
                'autocomplete' => true,
                'choices' => $this->templateRepository->getInitTemplates(),
                'attr'=> ['class' => 'custom-select custom-select-lg mb-3'],
                'placeholder' => 'Utiliser le template de la section'
            ])
            ->add('templateWidth', ChoiceType::class, [
                'choices' => $options['template_width'],
                'required' => true,
                'attr' => ['class' => 'custom-select custom-select-lg mb-3'],
                'label' => 'form.label.template_width',
            ])
            ->add('content', CKEditor5Type::class, [
                'required' => false,
                'label' => 'form.label.content',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // $resolver->setRequired('section');
        $resolver->setDefaults([
            'data_class' => Post::class,
            'label_attr' => [
                'class' => 'text-warning-emphasis ',
            ],
            'template_width'=>  [
                '1/12' => '1',
                '2/12' => '2',
                '3/12' => '3',
                '4/12' => '4',
                '5/12' => '5',
                '6/12' => '6',
                '7/12' => '7',
                '8/12' => '8',
                '9/12' => '9',
                '10/12' => '10',
                '11/12' => '11',
                '12/12' => '12',
            ],
        ]);
    }
}
