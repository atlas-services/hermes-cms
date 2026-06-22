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
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: \App\Repository\SectionRepository::class)]
class Section implements PositionableInterface
{
    use IdTrait;
    use PositionTrait;
    use ActiveTrait;

    #[ORM\ManyToOne(targetEntity: Menu::class, inversedBy: 'sections')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
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

    /** Effet GSAP image-reveal (folio2) : code effet ou « aucun ». */
    #[ORM\Column(name: 'template_gsap_image_effect', type: 'string', length: 32, nullable: true)]
    private ?string $templateGsapImageEffect = null;

    /** Locale explicite pour les sections footer (sans menu). */
    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    #[Assert\Locale]
    private ?string $locale = null;

    /** Clé stable entre langues (footer) ; optionnel sur sections de page. */
    #[ORM\Column(name: 'reference_name', type: 'string', length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $referenceName = null;

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

    public function isFooterSection(): bool
    {
        return 'footer_template' === strtolower(trim((string) ($this->template?->getCode() ?? '')));
    }

    public function isTopbarSection(): bool
    {
        return 'topbar_template' === strtolower(trim((string) ($this->template?->getCode() ?? '')));
    }

    public function isGlobalSection(): bool
    {
        return $this->isFooterSection() || $this->isTopbarSection();
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = $locale !== null && $locale !== '' ? $locale : null;

        return $this;
    }

    public function getReferenceName(): ?string
    {
        return $this->referenceName;
    }

    public function setReferenceName(?string $referenceName): self
    {
        $normalized = strtolower(trim((string) $referenceName));
        $this->referenceName = $normalized !== '' ? $normalized : null;

        return $this;
    }

    public function ensureFooterReferenceName(): self
    {
        return $this->ensureGlobalReferenceName('footer');
    }

    public function ensureGlobalReferenceName(string $prefix): self
    {
        if ($this->referenceName !== null && $this->referenceName !== '') {
            return $this;
        }
        $normalizedPrefix = strtolower(trim($prefix)) ?: 'section';
        $this->referenceName = $normalizedPrefix . '-' . ($this->getId() ?? uniqid());

        return $this;
    }

    public function getEffectiveLocale(): string
    {
        if ($this->menu !== null) {
            return $this->menu->getLocale() ?? 'fr';
        }

        return $this->locale ?? 'fr';
    }

    #[Assert\Callback]
    public function validateMenuRelation(ExecutionContextInterface $context): void
    {
        if ($this->isGlobalSection()) {
            if ($this->menu !== null) {
                $context->buildViolation('Une section globale ne doit pas être rattachée à une page menu.')
                    ->atPath('menu')
                    ->addViolation();
            }
            if ($this->locale === null || trim($this->locale) === '') {
                $context->buildViolation('Une section globale doit avoir une langue.')
                    ->atPath('locale')
                    ->addViolation();
            }

            return;
        }

        if ($this->menu === null) {
            $context->buildViolation('La section doit être rattachée à une page menu.')
                ->atPath('menu')
                ->addViolation();
        }
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

        return $this;
    }

    public function getTemplateBgcolor(): string
    {
        if ($this->isTransparent()) {
            return 'transparent';
        }

        if ($this->template_bgcolor === null || $this->template_bgcolor === '') {
            return 'transparent';
        }

        return $this->template_bgcolor;
    }

    public function setTemplateBgcolor(?string $template_bgcolor): self
    {
        $normalized = $template_bgcolor !== null ? trim($template_bgcolor) : null;
        if ($normalized === '' || $normalized === 'transparent') {
            $this->template_bgcolor = null;
        } else {
            $this->template_bgcolor = $normalized;
            $this->transparent = false;
        }

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

    public function getTemplateGsapImageEffect(): ?string
    {
        return $this->templateGsapImageEffect;
    }

    public function setTemplateGsapImageEffect(?string $templateGsapImageEffect): self
    {
        $normalized = $templateGsapImageEffect !== null ? trim($templateGsapImageEffect) : null;
        $this->templateGsapImageEffect = $normalized !== '' ? $normalized : null;

        return $this;
    }

    public function isFolioDynamique(): bool
    {
        return 'folio2' === strtolower(trim((string) ($this->template?->getCode() ?? '')));
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
