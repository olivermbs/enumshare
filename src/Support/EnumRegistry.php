<?php

namespace Olivermbs\Enumshare\Support;

use ReflectionClass;

class EnumRegistry
{
    public function __construct(
        protected array $enums = [],
        protected ?EnumAutoDiscovery $autoDiscovery = null,
        protected ?EnumValidator $validator = null
    ) {}

    public function manifest(?string $locale = null): array
    {
        $manifest = [];
        $allEnums = $this->getAllEnums();

        foreach ($allEnums as $enumClass) {
            if (! $this->isValidEnum($enumClass)) {
                continue;
            }

            $reflection = new ReflectionClass($enumClass);
            $shortName = $reflection->getShortName();

            $manifest[$shortName] = $enumClass::forFrontend($locale);
        }

        ksort($manifest);

        return $manifest;
    }

    protected function getAllEnums(): array
    {
        $configuredEnums = array_merge(
            $this->enums,
            config('enumshare.enums', [])
        );

        $discoveredEnums = [];
        if ($this->autoDiscovery && $this->isAutoDiscoveryEnabled()) {
            $discoveredEnums = $this->autoDiscovery->discover();
        }

        $allEnums = array_unique(array_merge($configuredEnums, $discoveredEnums));
        sort($allEnums);

        return $allEnums;
    }

    protected function isAutoDiscoveryEnabled(): bool
    {
        return config('enumshare.auto_discovery', false);
    }

    protected function isValidEnum(string $enumClass): bool
    {
        if ($this->validator) {
            return $this->validator->isValidEnumForExport($enumClass);
        }

        if (! class_exists($enumClass)) {
            return false;
        }

        $reflection = new ReflectionClass($enumClass);

        return $reflection->isEnum();
    }
}
