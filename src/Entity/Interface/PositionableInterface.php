<?php
declare(strict_types=1);

namespace App\Entity\Interface;

interface PositionableInterface
{
    public function setPosition(int $position): static;
}
