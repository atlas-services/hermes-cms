<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

trait ActiveTrait
{
    #[ORM\Column(type: 'boolean', nullable: false)]
    protected bool $active = true;

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }
}
