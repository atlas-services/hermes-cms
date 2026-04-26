<?php

namespace App\Repository;

use App\Entity\Menu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
    }

    public function save(Menu $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function findRoots(): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.children', 'c')
            ->addSelect('c')
            ->where('m.parent IS NULL')
            ->orderBy('m.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findChildren(Menu $menu): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.parent = :menu')
            ->setParameter('menu', $menu)
            ->orderBy('m.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 📍 Dernière position dans un niveau
     */
    public function getNextPosition(?Menu $parent): int
    {
        $qb = $this->createQueryBuilder('m')
            ->select('COALESCE(MAX(m.position), 0)');

        if ($parent === null) {
            $qb->andWhere('m.parent IS NULL');
        } else {
            $qb->andWhere('m.parent = :parent')
               ->setParameter('parent', $parent);
        }

        return ((int) $qb->getQuery()->getSingleScalarResult()) + 1;
    }
}
