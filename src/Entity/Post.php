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

    #[ORM\Column(type: 'boolean', nullable: true)]
    protected ?bool $transparent = null;

    #[ORM\Column(type: 'string', nullable: true)]
    protected ?string $template_bgcolor = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    protected ?int $template_nb_col = null;

    #[ORM\Column(type: 'string', nullable: true)]
    protected ?string $template_image_filter = null;

    public function __toString(): string
    {
        return $this->name;
    }

    // -------------------------
    // SECTION (remplace Menu)
    // -------------------------

    public function getSection(): ?Section
    {
        return $this->section;
    }

    public function setSection(?Section $section): self
    {
        $this->section = $section;

        return $this;
    }

    // -------------------------
    // TEMPLATE CONFIG (héritage possible)
    // -------------------------

    public function isTransparent(): bool
    {
        return $this->transparent ?? false;
    }

    public function setTransparent(bool $transparent): void
    {
        $this->transparent = $transparent;

        if ($transparent) {
            $this->template_bgcolor = 'transparent';
        }
    }

    public function getTemplateBgcolor(): string
    {
        if ($this->template_bgcolor === null || $this->isTransparent()) {
            return 'transparent';
        }

        return $this->template_bgcolor;
    }

    public function setTemplateBgcolor(?string $template_bgcolor): void
    {
        $this->template_bgcolor = $template_bgcolor;
    }

    public function getTemplateNbCol(): int
    {
        return $this->template_nb_col ?? 4;
    }

    public function setTemplateNbCol(?int $template_nb_col): void
    {
        $this->template_nb_col = $template_nb_col;
    }

    public function getTemplateImageFilter(): string
    {
        return $this->template_image_filter ?? 'bd_154';
    }

    public function setTemplateImageFilter(?string $template_image_filter): void
    {
        $this->template_image_filter = $template_image_filter;
    }
}
