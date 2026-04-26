<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\UX\StimulusBundle\Helper\StimulusHelper;

final class CKEditor5Type extends AbstractType
{
    public function __construct(
        #[Autowire(service: 'stimulus.helper')]
        private readonly StimulusHelper $stimulusHelper,
    ) {}

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        parent::finishView($view, $form, $options);

        $attr = $this->stimulusHelper->createStimulusAttributes();
        $attr->addController('ckeditor5');

        $view->vars['attr'] = $attr->toArray();
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }
}
