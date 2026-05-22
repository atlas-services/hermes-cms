<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

trait SlugTrait
{
    /**
     * @var string
     */
    #[ORM\Column(length: 80)]
    #[Gedmo\Slug(fields: ['name'])]
    private ?string $slug = null;

    public function getSlug(): ?string
    {
        return $this->slug;
    }

}
