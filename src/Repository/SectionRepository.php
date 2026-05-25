<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Section;
use App\Entity\Template;
use App\Service\FooterSectionService;
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
    public function findFooterSections(): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.template', 't')
            ->leftJoin('s.posts', 'p')
            ->addSelect('p')
            ->andWhere('s.menu IS NULL')
            ->andWhere('t.code = :code')
            ->setParameter('code', FooterSectionService::TEMPLATE_CODE)
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getNextFooterPosition(): int
    {
        $max = (int) $this->createQueryBuilder('s')
            ->select('COALESCE(MAX(s.position), 0)')
            ->innerJoin('s.template', 't')
            ->andWhere('s.menu IS NULL')
            ->andWhere('t.code = :code')
            ->setParameter('code', FooterSectionService::TEMPLATE_CODE)
            ->getQuery()
            ->getSingleScalarResult();

        return $max + 1;
    }
}
