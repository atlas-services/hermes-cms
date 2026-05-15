<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Interface\PositionableInterface;
use App\Entity\Traits\ActiveTrait;
use App\Entity\Traits\IdTrait;
use App\Entity\Traits\PositionTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Section implements PositionableInterface
{
    use IdTrait;
    use PositionTrait;
    use ActiveTrait;

    #[ORM\ManyToOne(targetEntity: Menu::class, inversedBy: 'sections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Menu $menu = null;

    #[ORM\ManyToOne(targetEntity: Template::class, inversedBy: 'sections')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Template $template = null;

    /** Largeur grille Bootstrap (1–12) pour les posts de la section. */
    #[ORM\Column(name: 'template_width', type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 12)]
    private ?int $templateWidth = null;

    /** Template de présentation secondaire (ex. modale1 / modale2), comme Hermes 2.x sur la section. */
    #[ORM\ManyToOne(targetEntity: Template::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Template $template2 = null;

    #[ORM\Column(name: 'template2_width', type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 12)]
    private ?int $template2Width = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $transparent = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $template_bgcolor = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 12)]
    private ?int $template_nb_col = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $template_image_filter = null;

    /** @var Collection<int, Post> */
    #[ORM\OneToMany(mappedBy: 'section', targetEntity: Post::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $posts;

    public function __construct()
    {
        $this->posts = new ArrayCollection();
    }

    // -------------------------
    // MENU
    // -------------------------

    public function getMenu(): ?Menu
    {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): self
    {
        $this->menu = $menu;

        return $this;
    }

    // -------------------------
    // TEMPLATE
    // -------------------------

    public function getTemplate(): ?Template
    {
        return $this->template;
    }

    public function setTemplate(?Template $template): self
    {
        $this->template = $template;

        return $this;
    }

    public function getTemplateWidth(): ?int
    {
        return $this->templateWidth;
    }

    public function setTemplateWidth(?int $templateWidth): self
    {
        $this->templateWidth = $templateWidth;

        return $this;
    }

    public function getTemplate2(): ?Template
    {
        return $this->template2;
    }

    public function setTemplate2(?Template $template2): self
    {
        $this->template2 = $template2;

        return $this;
    }

    public function getTemplate2Width(): int
    {
        return $this->template2Width ?? 4;
    }

    /** Valeur persistée (1–12) ou null = défaut côté {@see getTemplate2Width()} / config front. */
    public function getRawTemplate2Width(): ?int
    {
        return $this->template2Width;
    }

    public function setTemplate2Width(?int $template2Width): self
    {
        $this->template2Width = $template2Width;

        return $this;
    }

    // -------------------------
    // OPTIONS FOLIO / LISTE
    // -------------------------

    public function isTransparent(): bool
    {
        return $this->transparent ?? false;
    }

    public function setTransparent(bool $transparent): self
    {
        $this->transparent = $transparent;

        if ($transparent) {
            $this->template_bgcolor = 'transparent';
        }

        return $this;
    }

    public function getTemplateBgcolor(): string
    {
        if ($this->template_bgcolor === null || $this->isTransparent()) {
            return 'transparent';
        }

        return $this->template_bgcolor;
    }

    public function setTemplateBgcolor(?string $template_bgcolor): self
    {
        $this->template_bgcolor = $template_bgcolor;

        return $this;
    }

    public function getRawTemplateBgcolor(): ?string
    {
        return $this->template_bgcolor;
    }

    public function getTemplateNbCol(): int
    {
        return $this->template_nb_col ?? 4;
    }

    public function setTemplateNbCol(?int $template_nb_col): self
    {
        $this->template_nb_col = $template_nb_col;

        return $this;
    }

    public function getTemplateImageFilter(): string
    {
        return $this->template_image_filter ?? 'bd_154';
    }

    public function setTemplateImageFilter(?string $template_image_filter): self
    {
        $this->template_image_filter = $template_image_filter;

        return $this;
    }

    // -------------------------
    // POSTS
    // -------------------------

    /**
     * @return Collection<int, Post>
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(Post $post): self
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->setSection($this);
        }

        return $this;
    }

    public function removePost(Post $post): self
    {
        if ($this->posts->removeElement($post)) {
            if ($post->getSection() === $this) {
                $post->setSection(null);
            }
        }

        return $this;
    }
}
