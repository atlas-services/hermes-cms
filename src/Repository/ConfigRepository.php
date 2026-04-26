<?php

namespace App\Repository;

use App\Entity\Config;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Config>
 *
 * @method Config|null find($id, $lockMode = null, $lockVersion = null)
 * @method Config|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Config[]    findAll()
 * @method Config[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class ConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Config::class);
    }

    public function getQbConfigByTypeOrderByPosition(?string $type): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c');

        if ($type !== null) {
            $qb->where('c.type = :type')
            ->setParameter('type', $type);
        }

        return $qb->addOrderBy('c.position', 'ASC');
    }

    /**
     * @return Config[]
     */
    public function getConfigByTypeOrderByPosition(?string $type): array
    {
        return $this->getQbConfigByTypeOrderByPosition($type)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, mixed>
     */
    public function getActiveConfig(): array
    {
        /** @var Config[] $configs */
        $configs = $this->createQueryBuilder('c')
            ->where('c.active = true')
            ->getQuery()
            ->getResult();

        $configSimple = [];

        foreach ($configs as $conf) {
            $configSimple[$conf->getCode()] = $conf->getValue();

            if (in_array($conf->getCode(), ['bg_image', 'favicon', 'accueil', 'logo'], true)) {
                $configSimple[$conf->getCode()] = $conf;
            }
        }

        return $configSimple;
    }
}
