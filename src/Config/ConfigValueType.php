<?php

declare(strict_types=1);

namespace App\Config;

enum ConfigValueType: string
{
    case Text = 'text';
    case Choice = 'choice';
    case Boolean = 'boolean';
    case Color = 'color';
    case Width = 'width';
    case FontFamily = 'font_family';
}
