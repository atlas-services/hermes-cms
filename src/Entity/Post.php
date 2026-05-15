<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Interface\ActivableInterface;
use App\Entity\Interface\PositionableInterface;
use App\Entity\Traits\ActiveTrait;
use App\Entity\Traits\IdTrait;
use App\Entity\Traits\LocaleTrait;
use App\Entity\Traits\NameTrait;
use App\Entity\Traits\PositionTrait;
use App\Entity\Traits\PublishedTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[Vich\Uploadable]
#[ORM\Table(name: 'post')]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['section', 'name'], errorPath: 'name', message: 'post.exists')]
class Post extends AbstractContent implements PositionableInterface, ActivableInterface
{
    use IdTrait;
    use ActiveTrait;
    use PublishedTrait;
    use PositionTrait;
    use NameTrait;
    use LocaleTrait;

    #[ORM\ManyToOne(targetEntity: Section::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Section $section = null;

    public function __toString(): string
    {
        return $this->name;
    }

    public function getSection(): ?Section
    {
        return $this->section;
    }

    public function setSection(?Section $section): self
    {
        $this->section = $section;

        return $this;
    }
}
