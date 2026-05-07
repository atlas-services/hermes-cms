<?php

namespace App\Service;

use App\Entity\Menu;

class MenuNode
{
    public Menu $menu;

    public string $type;

    public string $slugPath;

    public bool $canAddPage = false;

    /** @var MenuNode[] */
    public array $children = [];
}
