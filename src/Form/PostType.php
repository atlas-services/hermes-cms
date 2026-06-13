<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use App\Entity\Template;
use App\Form\AbstractNameBaseType;
use App\Form\CKEditor5Type;
use App\Repository\TemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Form\Type\VichImageType;

class PostType extends AbstractNameBaseType
{
    public const TEMPLATE_TYPE_LISTE = 'liste';

    public const TEMPLATE_TYPE_LIBRE = 'libre';

    public const TEMPLATE_TYPE_FORMULAIRE = 'formulaire';

    public function __construct(
        private TemplateRepository $templateRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $options['label_name'] = 'global.name';
        $options['tooltip'] = 'Nom';
        $options['active'] = false;
        $options['name'] = false;
        parent::buildForm($builder, $options);

        $builder
            ->add('name', null, [
                'label' => 'form.label.name',
                'constraints' => [
                    new Assert\NotBlank(allowNull: false),
                ],
            ]);

        if (!($options['selected_section'] instanceof Section) && !$options['post_edit_mode']) {
            $builder->add('template', EntityType::class, [
                'class' => Template::class,
                'mapped' => false,
                'required' => false,
                'label' => 'form.label.post_template',
                'autocomplete' => true,
                'choices' => $this->templateRepository->getInitTemplates(),
                'attr' => ['class' => 'custom-select custom-select-lg mb-3'],
                'placeholder' => 'Utiliser le template de la section',
            ]);
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            $post = $event->getData();
            $form = $event->getForm();
            $type = $options['template_type'] !== null
                ? self::normalizePostTemplateType((string) $options['template_type'])
                : $this->resolveTemplateTypeForPost($post, $options['selected_section'] ?? null);

            if ($type === null && $options['menu'] instanceof Menu) {
                $this->addAmbiguousMenuNewFields($form);

                return;
            }

            $this->applyFieldsForTemplateType($form, $type, $post);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {
            if (($options['template_type'] ?? null) !== null || $options['post_edit_mode']) {
                return;
            }

            $data = $event->getData();
            if (!\is_array($data)) {
                return;
            }

            $form = $event->getForm();
            $type = $this->resolveTemplateTypeFromRaw($data);

            if ($type === null) {
                return;
            }

            if ($type === self::TEMPLATE_TYPE_LISTE) {
                if ($form->has('content')) {
                    $form->remove('content');
                }
                if ($form->has('imageFile')) {
                    $form->remove('imageFile');
                }
                unset($data['content'], $data['imageFile']);
                $event->setData($data);

                return;
            }

            if ($type === self::TEMPLATE_TYPE_LIBRE) {
                if ($form->has('imageFile')) {
                    $form->remove('imageFile');
                }
                unset($data['imageFile']);
                $event->setData($data);

                return;
            }

            if ($type === self::TEMPLATE_TYPE_FORMULAIRE) {
                if ($form->has('content')) {
                    $form->remove('content');
                }
                if ($form->has('imageFile')) {
                    $form->remove('imageFile');
                }
                unset($data['content'], $data['imageFile']);
                $event->setData($data);

                return;
            }

            if ($form->has('content')) {
                $form->remove('content');
            }
            if ($form->has('imageFile')) {
                $form->remove('imageFile');
            }
            unset($data['content'], $data['imageFile']);

            $post = $form->getData();
            $this->applyFieldsForTemplateType($form, $type, $post instanceof Post ? $post : null);

            $event->setData($data);
        });

        if ($options['liste_bulk_import_save'] && !$options['post_edit_mode']) {
            $builder->add('saveAndImportImages', SubmitType::class, [
                'label' => 'admin.post_bulk.add_images',
                'translation_domain' => 'messages',
                'attr' => ['class' => 'btn btn-outline-primary'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
            'label_attr' => [
                'class' => 'text-warning-emphasis ',
            ],
            'menu' => null,
            'selected_section' => null,
            /** Type logique du template de section (ex. hermes_templates.yaml : liste, libre). Null = déduit ou formulaire menu ambigu. */
            'template_type' => null,
            'post_edit_mode' => false,
            /** Deuxième bouton « import médias » (création depuis menu) : image non obligatoire si ce bouton est utilisé. */
            'liste_bulk_import_save' => false,
            'validation_groups' => fn (FormInterface $form): array => $this->resolveValidationGroups($form),
        ]);
        $resolver->setAllowedTypes('liste_bulk_import_save', 'bool');
    }

    /**
     * @return list<string>
     */
    private function resolveValidationGroups(FormInterface $form): array
    {
        $post = $form->getData();
        if (!$post instanceof Post) {
            return ['Default'];
        }

        $type = self::normalizePostTemplateType(self::staticResolveTemplateTypeFromForm($form, $post));
        if ($type === self::TEMPLATE_TYPE_LIBRE) {
            return ['Default', 'content'];
        }

        if ($type === self::TEMPLATE_TYPE_LISTE) {
            return ['Default'];
        }

        return ['Default'];
    }

    private function addAmbiguousMenuNewFields(FormInterface $form): void
    {
        if (!$form->has('content')) {
            $form->add('content', CKEditor5Type::class, [
                'required' => false,
                'label' => 'form.label.content',
            ]);
        }
        if (!$form->has('imageFile')) {
            $form->add('imageFile', VichImageType::class, $this->vichPostImageFieldOptions());
        }
    }

    private function applyFieldsForTemplateType(FormInterface $form, ?string $type, ?Post $post): void
    {
        $type = self::normalizePostTemplateType($type);

        if ($type === self::TEMPLATE_TYPE_LISTE) {
            return;
        }

        if ($type === self::TEMPLATE_TYPE_LIBRE) {
            if (!$form->has('content')) {
                $form->add('content', CKEditor5Type::class, [
                    'required' => false,
                    'label' => 'form.label.content',
                ]);
            }

            return;
        }

        if ($type === self::TEMPLATE_TYPE_FORMULAIRE) {
            return;
        }

        if (!$form->has('content')) {
            $form->add('content', CKEditor5Type::class, [
                'required' => false,
                'label' => 'form.label.content',
            ]);
        }
        if (!$form->has('imageFile')) {
            $form->add('imageFile', VichImageType::class, $this->vichPostImageFieldOptions());
        }
    }

    /**
     * required=false côté formulaire : évite l’attribut HTML5 sur le fichier (blocage silencieux
     * du submit). La contrainte {@see ImageTrait} + les groupes de validation PostType s’en chargent.
     *
     * @return array<string, mixed>
     */
    private function vichPostImageFieldOptions(): array
    {
        return [
            'required' => false,
            'label' => 'global.image',
            'translation_domain' => 'messages',
            'download_uri' => false,
            'image_uri' => true,
            'asset_helper' => true,
        ];
    }

    private function resolveTemplateTypeForPost(?Post $post, mixed $selectedSection): ?string
    {
        if ($selectedSection instanceof Section) {
            return self::normalizePostTemplateType($selectedSection->getTemplate()?->getType());
        }

        if ($post?->getSection() !== null) {
            return self::normalizePostTemplateType($post->getSection()->getTemplate()?->getType());
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveTemplateTypeFromRaw(array $data): ?string
    {
        if (!empty($data['template'])) {
            $templateId = $data['template'];
            if (\is_object($templateId) && $templateId instanceof Template) {
                return self::normalizePostTemplateType($templateId->getType());
            }
            $id = filter_var($templateId, FILTER_VALIDATE_INT);
            if (false !== $id) {
                $template = $this->entityManager->find(Template::class, $id);
                if ($template instanceof Template) {
                    return self::normalizePostTemplateType($template->getType());
                }
            }
        }

        return null;
    }

    private static function staticResolveTemplateTypeFromForm(FormInterface $form, Post $post): ?string
    {
        if ($form->has('template')) {
            $template = $form->get('template')->getData();
            if ($template instanceof Template) {
                return self::normalizePostTemplateType($template->getType());
            }
        }

        return self::normalizePostTemplateType($post->getSection()?->getTemplate()?->getType());
    }

    private static function normalizePostTemplateType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }
        $t = strtolower(trim($type));

        return $t === '' ? null : $t;
    }
}
