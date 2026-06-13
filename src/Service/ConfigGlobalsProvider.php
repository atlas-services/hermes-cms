<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ConfigRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Configurations front/admin : valeurs actives en base, complétées par les défauts de config/hermes_configs.yaml.
 */
final class ConfigGlobalsProvider
{
    public function __construct(
        private readonly ConfigRepository $configRepository,
        /** @var array<string, array<string, array{summary?: string, value?: mixed, position?: int}>> */
        #[Autowire(param: 'configs')]
        private readonly array $configDefaults,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigs(): array
    {
        return array_merge($this->flattenDefaults(), $this->configRepository->getActiveConfig());
    }

    /**
     * @return array<string, mixed>
     */
    private function flattenDefaults(): array
    {
        $flat = [];

        foreach ($this->configDefaults as $configsByCode) {
            foreach ($configsByCode as $code => $definition) {
                $flat[(string) $code] = $definition['value'] ?? null;
            }
        }

        return $flat;
    }
}
