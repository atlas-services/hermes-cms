<?php

namespace App\Entity;

use App\Entity\Interface\ActivableInterface;
use App\Entity\Interface\PositionableInterface;
use App\Entity\Traits\ActiveTrait;
use App\Entity\Traits\CodeTrait;
use App\Entity\Traits\IdTrait;
use App\Entity\Traits\LocaleTrait;
use App\Entity\Traits\NameTrait;
use App\Entity\Traits\PositionTrait;
use App\Entity\Traits\SlugTrait;
use App\Entity\Traits\UpdatedTrait;
use App\Repository\MenuRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: MenuRepository::class)]
class Menu implements PositionableInterface, ActivableInterface
{
    use IdTrait;
    use CodeTrait;
    use ActiveTrait;
    use NameTrait;
    use SlugTrait;
    use PositionTrait;
    use LocaleTrait;
    use UpdatedTrait;

    public const MAX_DEPTH = 5;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?self $parent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(
        mappedBy: 'parent',
        targetEntity: self::class,
        cascade: ['persist', 'remove']
    )]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $children;


    /** @var Collection<int, Section> */
    #[ORM\OneToMany(
        mappedBy: 'menu',
        targetEntity: Section::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $sections;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->sections = new ArrayCollection();
    }

    // -------------------------
    // RELATIONS
    // -------------------------

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        if ($parent === $this) {
            throw new \DomainException('Un menu ne peut pas être son propre parent.');
        }

        $this->parent = $parent;

        return $this;
    }

    public function addChild(self $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    // -------------------------
    // SECTIONS (nouveau cœur métier)
    // -------------------------

    /**
     * @return Collection<int, Section>
     */
    public function getSections(): Collection
    {
        return $this->sections;
    }

    public function addSection(Section $section): self
    {
        if (!$this->isLeaf()) {
            throw new \DomainException('Seuls les menus sans enfants peuvent contenir des sections.');
        }

        if (!$this->sections->contains($section)) {
            $this->sections->add($section);
            $section->setMenu($this);
        }

        return $this;
    }

    public function removeSection(Section $section): self
    {
        if ($this->sections->removeElement($section)) {
            if ($section->getMenu() === $this) {
                $section->setMenu(null);
            }
        }

        return $this;
    }

    // -------------------------
    // MÉTIER STRUCTUREL
    // -------------------------
    public function getType(): string
    {
        if ($this->isRoot()) {
            return 'root';
        }

        if ($this->isPage()) {
            return 'page';
        }

        if ($this->isNavigation()) {
            return 'navigation';
        }

        return 'navigation';
    }


    public function isPage(): bool
    {
        return !$this->getSections()->isEmpty();
    }

    public function isNavigation(): bool
    {
        return !$this->getChildren()->isEmpty();
    }

    public function isLeaf(): bool
    {
        return $this->getChildren()->isEmpty();
    }

    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    public function isSubMenu(): bool
    {
        return $this->parent !== null;
    }

    public function getDepth(): int
    {
        $depth = 0;
        $parent = $this->parent;

        while ($parent !== null) {
            $depth++;
            $parent = $parent->getParent();
        }

        return $depth;
    }

    public function getParentOrSelf(): self
    {
        return $this->parent ?? $this;
    }

    public function getInitialParent(): self
    {
        $current = $this;
        $visited = [];

        while ($current->getParent() !== null) {
            if (in_array($current, $visited, true)) {
                throw new \LogicException('Boucle détectée dans l’arbre des menus');
            }

            $visited[] = $current;
            $current = $current->getParent();
        }

        return $current;
    }

    /**
     * @return self[]
     */
    public function getParents(): array
    {
        $parents = [];
        $current = $this->parent;

        while ($current !== null) {
            $parents[] = $current;
            $current = $current->getParent();
        }

        return array_reverse($parents);
    }

}
