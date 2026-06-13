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
            new TwigFilter('col_lg', $this->colLg(...)),
            new TwigFilter('nb_col', $this->nbCol(...)),
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
            static fn (Post $p): bool => $p->isActive()
        ));
        if ($posts === []) {
            return [];
        }

        $nbCol = $this->resolveNbCol($section);
        $total = \count($posts);
        $round = (int) round($total / $nbCol);
        if ($round < 1) {
            $round = 1;
        }

        /** @var list<list<Post>> $pictures */
        $pictures = array_chunk($posts, $round);

        if (isset($pictures[$nbCol])) {
            $lasts = array_pop($pictures);
            foreach ($lasts as $key => $picture) {
                if (!isset($pictures[$key])) {
                    $pictures[$key] = [];
                }
                $pictures[$key][] = $picture;
            }
        }

        return $pictures;
    }

    private function resolveNbCol(Section $section): int
    {
        return max(1, min(12, $section->getTemplateNbCol()));
    }

    /**
     * Port de Hermes 2.2.7 {@see AppExtension::colLg} : largeur de colonne Bootstrap (1–12).
     * Si une section est passée, utilise {@see Section::getTemplateNbCol()}.
     */
    public function colLg(mixed $prct, mixed $section = null): int
    {
        $v = (int) round((float) $prct);
        $v = max(1, min(12, $v));

        if ($section instanceof Section) {
            $nb = $section->getTemplateNbCol();
            if ($nb < 1) {
                return $v;
            }
            try {
                return max(1, min(12, (int) (12 / $nb)));
            } catch (\Throwable) {
                return 12;
            }
        }

        return $v;
    }

    /**
     * Port de Hermes 2.2.7 {@see AppExtension::nbCol} : nombre de colonnes BS pour N images par ligne.
     */
    public function nbCol(mixed $nbCol): int
    {
        $n = (int) $nbCol;

        return match (true) {
            $n === 1 => 12,
            $n === 2 => 6,
            $n === 3 => 4,
            $n === 4 => 3,
            $n >= 5 && $n <= 8 => 2,
            default => 1,
        };
    }
}
