<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Section;
use App\Entity\Template;
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
}
