<?php

namespace App\Entity;

use App\Entity\Section;
use App\Entity\Traits\ActiveTrait;
use App\Entity\Traits\CodeTrait;
use App\Entity\Traits\IdTrait;
use App\Entity\Traits\NameTrait;
use App\Entity\Traits\SummaryTrait;
use App\Entity\Traits\TypeTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'template')]
#[ORM\Entity]
class Template
{
    use IdTrait;
    use ActiveTrait;
    use TypeTrait;
    use CodeTrait;
    use NameTrait;
    use SummaryTrait;

    /**
     * @var Collection<int, Section>
     */
    #[ORM\OneToMany(mappedBy: 'template', targetEntity: Section::class)]
    private Collection $sections;

    public function __construct()
    {
        $this->sections = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->summary;
    }

    /**
     * @return Collection<int, Section>
     */
    public function getSections(): Collection
    {
        return $this->sections;
    }

    public function addSection(Section $section): self
    {
        if (!$this->sections->contains($section)) {
            $this->sections->add($section);
            $section->setTemplate($this);
        }

        return $this;
    }

    public function removeSection(Section $section): self
    {
        if ($this->sections->removeElement($section)) {
            if ($section->getTemplate() === $this) {
                $section->setTemplate(null);
            }
        }

        return $this;
    }
}
