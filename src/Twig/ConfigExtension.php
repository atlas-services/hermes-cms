<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\ConfigRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class ConfigExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ConfigRepository $configRepository,
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'configs' => $this->configRepository->getActiveConfig(),
        ];
    }
}
