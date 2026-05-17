<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

trait LocaleTrait
{
    #[ORM\Column(type: 'string', nullable: true, options: ['default' => 'fr'])]
    #[Assert\Locale]
    protected ?string $locale = 'fr';

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(string $locale = 'fr'): static
    {
        $this->locale = $locale;

        return $this;
    }
}
