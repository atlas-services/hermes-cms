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

    /**
     * @return Menu[]
     */
    public function findRootsByLocale(string $locale): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.children', 'c')
            ->addSelect('c')
            ->where('m.parent IS NULL')
            ->andWhere('m.locale = :locale OR (m.locale IS NULL AND :locale = :default)')
            ->setParameter('locale', $locale)
            ->setParameter('default', 'fr')
            ->orderBy('m.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByLocaleAndReferenceName(string $locale, string $referenceName): ?Menu
    {
        return $this->findOneBy([
            'locale' => $locale,
            'referenceName' => strtolower(trim($referenceName)),
        ]);
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

    /**
     * @return Menu[]
     */
    public function findPages(?string $locale = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->distinct()
            ->innerJoin('m.sections', 's')
            ->addSelect('s')
            ->leftJoin('s.posts', 'p')
            ->addSelect('p')
            ->orderBy('m.position', 'ASC')
            ->addOrderBy('m.name', 'ASC')
            ->addOrderBy('s.position', 'ASC')
            ->addOrderBy('p.position', 'ASC');

        if ($locale !== null) {
            $qb->andWhere('m.locale = :locale OR (m.locale IS NULL AND :locale = :default)')
                ->setParameter('locale', $locale)
                ->setParameter('default', 'fr');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Menu[]
     */
    public function findByLocale(string $locale): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.locale = :locale OR (m.locale IS NULL AND :locale = :default)')
            ->setParameter('locale', $locale)
            ->setParameter('default', 'fr')
            ->orderBy('m.position', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<string>
     */
    public function findDistinctLocales(): array
    {
        /** @var list<string|null> $rows */
        $rows = $this->createQueryBuilder('m')
            ->select('DISTINCT COALESCE(m.locale, :default)')
            ->setParameter('default', 'fr')
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_filter(
            array_map(static fn (?string $locale): string => strtolower(trim((string) $locale)), $rows),
            static fn (string $locale): bool => $locale !== '',
        ));
    }

    public function countByLocale(string $locale): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.locale = :locale OR (m.locale IS NULL AND :locale = :default)')
            ->setParameter('locale', $locale)
            ->setParameter('default', 'fr')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
