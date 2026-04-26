<?php
declare(strict_types=1);

namespace App\Entity\Interface;

interface ActivableInterface
{
    public function isActive(): bool;
    public function setActive(bool $active): static;
}
