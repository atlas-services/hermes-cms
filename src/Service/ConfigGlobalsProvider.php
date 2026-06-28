<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Config;
use App\Repository\ConfigRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Configurations front/admin : valeurs actives en base, complétées par les défauts de config/hermes_configs.yaml.
 */
final class ConfigGlobalsProvider
{
    private const STATEFUL_BOOLEAN_CONFIGS = [
        'nav_left_open_on_load',
        'topbar_dismiss_once',
    ];

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
        $configs = array_merge($this->flattenDefaults(), $this->configRepository->getActiveConfig());

        foreach (self::STATEFUL_BOOLEAN_CONFIGS as $code) {
            $config = $this->configRepository->findOneBy(['code' => $code]);
            if ($config instanceof Config) {
                $configs[$code] = $config->isActive() && $this->isTruthy($config->getValue());
            }
        }

        return $configs;
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

    private function isTruthy(mixed $value): bool
    {
        if ($value === true) {
            return true;
        }

        if ($value === false || $value === null) {
            return false;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
    }
}
