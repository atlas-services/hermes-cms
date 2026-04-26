<?php

namespace App\Repository;

use App\Entity\Menu;
use App\Entity\Post;
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
            $qb->where('p.menu = :menu')
                ->setParameter('menu', $menu);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
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
            ->orderBy('p.menu', 'ASC')
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
}
