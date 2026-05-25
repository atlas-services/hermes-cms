<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Section;
use App\Entity\Template;
use App\Repository\SectionRepository;
use App\Repository\TemplateRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Sections globales au gabarit « footer_template » (non rattachées à une page menu).
 */
final class FooterSectionService
{
    public const TEMPLATE_CODE = 'footer_template';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TemplateRepository $templateRepository,
        private readonly SectionRepository $sectionRepository,
    ) {
    }

    public function isFooterSection(Section $section): bool
    {
        return self::TEMPLATE_CODE === strtolower(trim((string) ($section->getTemplate()?->getCode() ?? '')));
    }

    public function createSection(): Section
    {
        $template = $this->templateRepository->findOneBy(['code' => self::TEMPLATE_CODE]);
        if (!$template instanceof Template) {
            throw new \DomainException('Gabarit footer_template introuvable.');
        }

        $section = new Section();
        $section->setMenu(null);
        $section->setTemplate($template);
        $section->setTemplateWidth(12);
        $section->setPosition($this->sectionRepository->getNextFooterPosition());

        $this->entityManager->persist($section);
        $this->entityManager->flush();

        return $section;
    }
}
