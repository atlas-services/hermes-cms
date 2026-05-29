<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

trait ReferenceNameTrait
{
    #[ORM\Column(name: 'reference_name', type: 'string', length: 100, options: ['default' => 'ref'])]
    #[Assert\Length(max: 100)]
    #[Assert\NotBlank]
    private string $referenceName = 'ref';

    public function getReferenceName(): string
    {
        return $this->referenceName;
    }

    public function setReferenceName(?string $referenceName): static
    {
        $normalized = strtolower(trim((string) $referenceName));
        $this->referenceName = $normalized !== '' ? $normalized : 'ref';

        return $this;
    }

    public function ensureReferenceNameFromLabel(?string $label): static
    {
        if ($this->referenceName !== '' && $this->referenceName !== 'ref') {
            return $this;
        }

        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/', '-', (string) $label), '-'));
        $this->referenceName = $slug !== '' ? $slug : 'ref';

        return $this;
    }
}
