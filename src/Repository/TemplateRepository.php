<?php

namespace App\Repository;

use App\Entity\Template;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Template>
 *
 * @method Template|null find($id, $lockMode = null, $lockVersion = null)
 * @method Template|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Template[]    findAll()
 * @method Template[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class TemplateRepository extends ServiceEntityRepository
{
    public const TEMPLATES_BASE = [
        'libre' => 'libre',
        'folio1' => 'folio1',
        'contact' => 'contact',
        'newsletter' => 'newsletter',
        'livredor1' => 'livredor1',
        'newsletter_template' => 'newsletter_template',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Template::class);
    }

    public function getQbTemplates(): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.name', 'ASC');
    }

    public function getQbTemplateByType(?string $type, bool $activeForm = true): QueryBuilder
    {
        if ($type !== null) {
            return $this->createQueryBuilder('s')
                ->where('s.type = :type')
                ->setParameter('type', $type);
        }

        return $this->getQbInitTemplates($activeForm);
    }

    /**
     * @return Template[]
     */
    public function getInitTemplates(bool $activeForm = true): array
    {
        return $this->getQbInitTemplates($activeForm)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Template[]
     */
    public function getTemplates(): array
    {
        return $this->getQbTemplates()
            ->getQuery()
            ->getResult();
    }

    public function getQbInitTemplates(bool $activeForm = true): QueryBuilder
    {
        $templateBase = self::TEMPLATES_BASE;

        if ($activeForm === false) {
            unset(
                $templateBase['contact'],
                $templateBase['newsletter'],
                $templateBase['livredor1']
            );
        }

        return $this->getQbTemplates()
            ->where('s.active = true')
            ->andWhere('s.code IN (:code)')
            ->setParameter('code', array_values($templateBase))
            ->orderBy('s.id', 'ASC');
    }

    public function getQbTemplate2(): QueryBuilder
    {
        return $this->getQbTemplates()
            ->where('s.code LIKE :modale')
            ->setParameter('modale', '%modale%')
            ->orderBy('s.id', 'ASC');
    }

    /**
     * Modale affichée par défaut sur une section (Hermes 2.x : template2 de section).
     */
    public function findDefaultModaleTemplate(): ?Template
    {
        return $this->findOneBy(['code' => 'modale1'])
            ?? $this->findOneBy(['code' => 'modale2']);
    }

    public function getQbTemplateLibre(): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->where('s.type = :libre')
            ->setParameter('libre', 'libre');
    }

    public function getTemplateLibre(): ?Template
    {
        return $this->getQbTemplateLibre()
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getQbTemplateListe(): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->where('s.code = :code')
            ->setParameter('code', 'folio1');
    }

    public function getQbTemplateLibreHms(): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->where('s.code LIKE :libre')
            ->setParameter('libre', '%hms%');
    }
}
