<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Post;
use App\Entity\Section;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Filtres Twig portés depuis Hermes 2.x ({@see https://github.com/atlas-services/hermes}).
 */
final class HermesExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('col_imgs', $this->colImgs(...)),
        ];
    }

    /**
     * Découpe les posts d’une section en colonnes pour les gabarits type folio / liste.
     * Logique alignée sur {@link https://raw.githubusercontent.com/atlas-services/hermes/release/2.2.7/src/Twig/AppExtension.php} colImgs().
     *
     * @return list<list<Post>>
     */
    public function colImgs(?Section $section): array
    {
        if (!$section instanceof Section) {
            return [];
        }

        if (!$section->isActive()) {
            return [];
        }

        $posts = array_values(array_filter(
            $section->getPosts()->toArray(),
            static fn ($p): bool => $p instanceof Post && $p->isActive()
        ));
        if ($posts === []) {
            return [];
        }

        $nbCol = $this->resolveNbCol($posts);
        $total = \count($posts);
        $round = (int) round($total / $nbCol);
        if ($round < 1) {
            $round = 1;
        }

        /** @var list<list<Post>> $pictures */
        $pictures = array_chunk($posts, $round);

        if (isset($pictures[$nbCol])) {
            $lasts = array_pop($pictures);
            if (\is_array($lasts)) {
                foreach ($lasts as $key => $picture) {
                    if (!isset($pictures[$key])) {
                        $pictures[$key] = [];
                    }
                    if ($picture instanceof Post) {
                        $pictures[$key][] = $picture;
                    }
                }
            }
        }

        return $pictures;
    }

    /**
     * @param list<Post> $activePosts
     */
    private function resolveNbCol(array $activePosts): int
    {
        foreach ($activePosts as $post) {
            if ($post instanceof Post) {
                $n = $post->getTemplateNbCol();

                return max(1, min(12, $n));
            }
        }

        return 3;
    }
}
