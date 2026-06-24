<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Interface\PositionableInterface;
use App\Entity\Traits\ActiveTrait;
use App\Entity\Traits\CodeTrait;
use App\Entity\Traits\ConfigTypeTrait;
use App\Entity\Traits\IdTrait;
use App\Entity\Traits\ImageTrait;
use App\Entity\Traits\PositionTrait;
use App\Entity\Traits\PublishedTrait;
use App\Entity\Traits\SummaryTrait;
use App\Entity\Traits\UpdatedTrait;
use App\Entity\Traits\ValueTrait;
use App\Repository\ConfigRepository;
use Doctrine\ORM\Mapping as ORM;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[Vich\Uploadable]
#[ORM\Table(name: 'config')]
#[ORM\Entity(repositoryClass: ConfigRepository::class)]
class Config implements PositionableInterface
{
    use IdTrait;
    use CodeTrait;
    use ConfigTypeTrait;
    use ValueTrait;
    use SummaryTrait;
    use ImageTrait;
    use UpdatedTrait;
    use PublishedTrait;
    use ActiveTrait;
    use PositionTrait;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $publishedAt;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $transparent = null;

    public function __construct()
    {
        $this->publishedAt = new \DateTime();
    }

    public function __toString(): string
    {
        return $this->summary;
    }

    public function __get(string $prop): mixed
    {
        return $this->$prop ?? null;
    }

    public function __isset(string $prop): bool
    {
        return isset($this->$prop);
    }

    public function getTransparent(): ?bool
    {
        return $this->transparent;
    }

    public function setTransparent(?bool $transparent): self
    {
        $this->transparent = $transparent;
        return $this;
    }
}
