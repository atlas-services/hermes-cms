<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Section;
use App\Entity\Template;
use App\Service\FooterSectionService;
use App\Service\TopbarSectionService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Section>
 */
class SectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Section::class);
    }

    /**
     * Sections dont le gabarit est « newsletter_template » (campagnes newsletter).
     *
     * @return list<Section>
     */
    public function findNewsletterCampaignSections(): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.template', 't')
            ->andWhere('t.code = :code')
            ->setParameter('code', 'newsletter_template')
            ->orderBy('s.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sections footer globales (sans menu), triées par position.
     *
     * @return list<Section>
     */
    public function findFooterSections(?string $locale = null): array
    {
        return $this->findGlobalSectionsByTemplateCode(FooterSectionService::TEMPLATE_CODE, $locale);
    }

    /**
     * @return list<Section>
     */
    public function findTopbarSections(?string $locale = null): array
    {
        return $this->findGlobalSectionsByTemplateCode(TopbarSectionService::TEMPLATE_CODE, $locale);
    }

    /**
     * @return list<Section>
     */
    private function findGlobalSectionsByTemplateCode(string $templateCode, ?string $locale = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->innerJoin('s.template', 't')
            ->leftJoin('s.posts', 'p')
            ->addSelect('p')
            ->andWhere('s.menu IS NULL')
            ->andWhere('t.code = :code')
            ->setParameter('code', $templateCode)
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('p.position', 'ASC');

        if ($locale !== null) {
            $qb->andWhere('s.locale = :locale')
                ->setParameter('locale', $locale);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Section>
     */
    public function findFooterSectionsForLocale(string $locale): array
    {
        return $this->findFooterSections($locale);
    }

    /**
     * @return list<Section>
     */
    public function findTopbarSectionsForLocale(string $locale): array
    {
        return $this->findTopbarSections($locale);
    }

    public function findOneFooterByLocaleAndReference(string $locale, string $referenceName): ?Section
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.template', 't')
            ->andWhere('s.menu IS NULL')
            ->andWhere('t.code = :code')
            ->andWhere('s.locale = :locale')
            ->andWhere('s.referenceName = :ref')
            ->setParameter('code', FooterSectionService::TEMPLATE_CODE)
            ->setParameter('locale', $locale)
            ->setParameter('ref', strtolower(trim($referenceName)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getNextFooterPosition(?string $locale = null): int
    {
        return $this->getNextGlobalPosition(FooterSectionService::TEMPLATE_CODE, $locale);
    }

    public function getNextTopbarPosition(?string $locale = null): int
    {
        return $this->getNextGlobalPosition(TopbarSectionService::TEMPLATE_CODE, $locale);
    }

    private function getNextGlobalPosition(string $templateCode, ?string $locale = null): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COALESCE(MAX(s.position), 0)')
            ->innerJoin('s.template', 't')
            ->andWhere('s.menu IS NULL')
            ->andWhere('t.code = :code')
            ->setParameter('code', $templateCode);

        if ($locale !== null) {
            $qb->andWhere('s.locale = :locale')
                ->setParameter('locale', $locale);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() + 1;
    }
}
