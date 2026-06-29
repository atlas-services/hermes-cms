<?php

namespace App\Form;

use App\Config\ConfigDefinition;
use App\Config\ConfigDefinitionRegistry;
use App\Config\ConfigValueNormalizer;
use App\Config\ConfigValueType;
use App\Entity\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigType extends AbstractType
{
    public function __construct(
        private readonly ConfigDefinitionRegistry $configDefinitionRegistry,
        private readonly ConfigValueNormalizer $configValueNormalizer,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, ['disabled' => $options['code_disabled']])
            ->add('position', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', [
                'required' => false,
                'attr' => [
                    'min' => 0,
                    'max' => 99,                       
                    'class' => 'custom-select custom-select-lg mb-3 ',
                    'label' => 'global.position',
                ],
                'choices' => range(0, 99),
            ])
            ->add('type', ChoiceType::class, [
                'choices' => $options['type_choices'],
                'disabled' => $options['disable_type'],
                'attr' => ['class' => 'form-select'],
            ]);

        if ($options['show_active']) {
            $builder->add('active', CheckboxType::class, [
                'required' => false,
                'label' => 'form.label.active',
                'attr' => [
                    'class' => 'form-check-input ',
                ],
                'label_attr' => $options['label_attr']
            ]);
        }
        if (null != $options['value_choices']) {
            $builder
                ->add('value', ChoiceType::class, [
                    'choices' => $options['value_choices'],
                    'attr' => ['class' => 'select2 custom-select select2 custom-select-lg mb-3']
                ]);
        }
        $builder
            ->add('summary');
        if ($options['type_image']) {
            $builder
                ->add('imageFile', 'Vich\UploaderBundle\Form\Type\VichImageType', [
                    'required' => false,
                    'label' => 'global.image',
                    'translation_domain' => 'messages',
                    'download_uri' => false,
                ]);
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $data = $event->getData();
            if (!$data instanceof Config || $data->getCode() === null) {
                return;
            }

            $definition = $this->configDefinitionRegistry->definitionFor($data->getCode());
            if ($definition->type === ConfigValueType::Boolean) {
                $data->setValue($this->configValueNormalizer->normalizeForStorage($definition, $data->getValue()));
            }

            $this->addValueField($event->getForm(), $definition);
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            if (!$data instanceof Config || $data->getCode() === null) {
                return;
            }

            $definition = $this->configDefinitionRegistry->definitionFor($data->getCode());
            if ($definition->type === ConfigValueType::Boolean) {
                $enabled = $this->configValueNormalizer->toBool($data->getValue());
                $data->setValue($this->configValueNormalizer->normalizeForStorage($definition, $data->getValue()));

                if ($definition->statefulBoolean) {
                    $data->setActive($enabled);
                }
            }

            if ($data->transparent) {
                $data->setValue('transparent');
            }
        });

    }

    private function addValueField(FormInterface $form, ConfigDefinition $definition): void
    {
        match ($definition->type) {
            ConfigValueType::Choice, ConfigValueType::Boolean => $form->add('value', ChoiceType::class, [
                'choices' => $definition->choices,
                'attr' => ['class' => 'custom-select custom-select-lg mb-3'],
            ]),
            ConfigValueType::Color => $this->addColorFields($form),
            ConfigValueType::Width => $form->add('value', ChoiceType::class, [
                'required' => false,
                'choices' => $this->configDefinitionRegistry->widthChoices(),
                'attr' => ['class' => 'custom-select custom-select-lg mb-3'],
            ]),
            ConfigValueType::FontFamily => $form->add('value', ChoiceType::class, [
                'required' => false,
                'choices' => $this->configDefinitionRegistry->fontFamilyChoices(),
                'attr' => ['class' => 'custom-select custom-select-lg mb-3'],
            ]),
            ConfigValueType::Text => $form->add('value', TextType::class, [
                'required' => false,
            ]),
        };
    }

    private function addColorFields(FormInterface $form): void
    {
        $form->add('value', ColorType::class, [
            'required' => false,
        ]);
        $form->add('transparent', ChoiceType::class, [
            'choices' => [
                'translation.no' => false,
                'translation.yes' => true,
            ],
            'choice_translation_domain' => 'messages',
            'attr' => ['class' => 'form-select'],
        ]);
    }



    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
            'active' => true,
            'image_file' => true,
            'code_disabled' => true,
            'value_choices' => null,
            'type_image' => false,
            'show_active' => false,
            'type_choices' => [
                'admin' => 'admin',
                'head' => 'head',
                'générale' => 'site',
                'contenu' => 'content',
                'footer' => 'footer',
                'haut avant menu' => 'topbar',
                'contact' => 'contact',
                'newsletter' => 'newsletter',
                'livredor' => 'livredor',
                'image' => 'image',
                'menu' => 'nav',
                'folio' => 'folio',
                'carousel' => 'carousel',
                'carte' => 'card',
                'modale' => 'modale',
                'réseaux sociaux' => 'network',
                'nd' => null,
            ],
            'nav_bar_choices' => [
                'base' => 'base',
                'left' => 'left',
                'full' => 'full',
            ],
            'disable_type' => false,
        ]);
    }

}
