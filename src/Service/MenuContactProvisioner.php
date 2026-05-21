<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Menu;
use App\Entity\Section;
use App\Entity\Template;
use App\Repository\TemplateRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Menu nommé « contact » (toute casse) → page formulaire contact (section template contact).
 */
final class MenuContactProvisioner
{
    public const CONTACT_MENU_NAME = 'contact';

    public function __construct(
        private readonly TemplateRepository $templateRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function isContactMenuName(?string $name): bool
    {
        return $name !== null && strtolower(trim($name)) === self::CONTACT_MENU_NAME;
    }

    /**
     * Crée la section formulaire contact si le menu vient d’être créé et n’a pas encore de section.
     */
    public function provisionIfContactMenu(Menu $menu): bool
    {
        if (!$this->isContactMenuName($menu->getName())) {
            return false;
        }

        if (!$menu->getSections()->isEmpty()) {
            return false;
        }

        $template = $this->templateRepository->findOneBy(['code' => 'contact', 'active' => true]);
        if (!$template instanceof Template) {
            return false;
        }

        $section = new Section();
        $section->setMenu($menu);
        $section->setTemplate($template);
        $section->setTemplateWidth(10);
        $section->setPosition(1);

        $defaultModale = $this->templateRepository->findDefaultModaleTemplate();
        if ($defaultModale !== null) {
            $section->setTemplate2($defaultModale);
        }

        $menu->addSection($section);
        $this->entityManager->persist($section);
        $this->entityManager->flush();

        return true;
    }
}
