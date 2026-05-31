<?php

namespace App\Repository;

use App\Entity\Menu;
use App\Entity\Post;
use App\Entity\Section;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function save(Post $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    public function getMaxPosition(?Menu $menu): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COALESCE(MAX(p.position), 0)');

        if ($menu !== null) {
            $qb->join('p.section', 's')
                ->andWhere('s.menu = :menu')
                ->setParameter('menu', $menu);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Plus fiable que {@see Section::getPosts()}::count() : la collection peut inclure
     * un post non encore flushé ou être partiellement initialisée selon le contexte de chargement.
     */
    public function getMaxPositionInSection(Section $section): int
    {
        // Section sans identifiant (persistée mais pas encore flushée) : aucun post en base ne la référence encore.
        if ($section->getId() === null) {
            return 0;
        }

        $v = $this->createQueryBuilder('p')
            ->select('COALESCE(MAX(p.position), 0)')
            ->where('p.section = :sectionId')
            ->setParameter('sectionId', $section->getId())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $v;
    }

    public function getLastPostPosition(): int
    {
        $result = $this->createQueryBuilder('p')
            ->select('MAX(p.position) as max_position')
            ->getQuery()
            ->getOneOrNullResult();

        return (int) ($result['max_position'] ?? 0);
    }

    public function switchActive(int $id): Post
    {
        $post = $this->find($id);

        if (!$post instanceof Post) {
            throw new NotFoundHttpException('Post not found');
        }

        $post->setActive(!$post->isActive());

        $this->getEntityManager()->flush();

        return $post;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getEditablePosts(): array
    {
        $posts = [];

        $allPosts = $this->createQueryBuilder('p')
            ->join('p.section', 'section')
            ->join('section.menu', 'menu')
            ->orderBy('menu.name', 'ASC')
            ->addOrderBy('p.position', 'ASC')
            ->addOrderBy('p.active', 'DESC')
            ->getQuery()
            ->getResult();

        foreach ($allPosts as $key => $post) {
            if (!$post instanceof Post || $post->getSection() === null) {
                continue;
            }

            $section = $post->getSection();
            $menu = $section->getMenu();

            if ($menu === null) {
                continue;
            }

            $posts[$key] = [
                'id' => $post->getId(),
                'active' => $post->isActive(),
                'position' => $post->getPosition(),
                'name' => $post->getName(),
                'startPublishedAt' => $post->getStartPublishedAt(),
                'endPublishedAt' => $post->getEndPublishedAt(),
                'updatedAt' => $post->getUpdatedAt(),

                'menu' => $menu->getName(),
                'menu_id' => $menu->getId(),
                'template' => $section->getTemplate()?->getName(),
                'template_code' => $section->getTemplate()?->getCode(),

                'menu_slug' => $menu->getSlug(),
                'locale' => $menu->getLocale(),
                'menu_parent' => $menu->getParent()?->getName(),
                'sheet' => $menu->getInitialParent()->getName(),
            ];
        }

        return array_values($posts);
    }

    /**
     * @return Post[]
     */
    public function getPosts(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.locale', 'ASC')
            ->addOrderBy('p.section', 'ASC')
            ->addOrderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<string>
     */
    public function findDistinctLocales(): array
    {
        /** @var list<string|null> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT COALESCE(p.locale, m.locale, :default)')
            ->innerJoin('p.section', 's')
            ->leftJoin('s.menu', 'm')
            ->setParameter('default', 'fr')
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_filter(
            array_map(static fn (?string $locale): string => strtolower(trim((string) $locale)), $rows),
            static fn (string $locale): bool => $locale !== '',
        ));
    }
}
