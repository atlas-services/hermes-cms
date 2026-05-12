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

    #[ORM\ManyToOne(targetEntity: Template::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Template $template = null;

    /** Largeur grille Bootstrap (1–12) pour les posts de la section. */
    #[ORM\Column(name: 'template_width', type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 12)]
    private ?int $templateWidth = null;

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
