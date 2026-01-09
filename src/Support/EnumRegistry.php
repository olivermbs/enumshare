<?php

namespace Olivermbs\Enumshare\Support;

use Olivermbs\Enumshare\Exceptions\InvalidEnumException;
use ReflectionClass;

class EnumRegistry
{
    public function __construct(
        protected array $enums = [],
        protected ?EnumAutoDiscovery $autoDiscovery = null,
        protected ?EnumValidator $validator = null,
        protected ?EnumExtractor $extractor = null
    ) {}

    public function manifest(?string $locale = null): array
    {
        $manifest = [];
        $seen = [];
        $extractor = $this->extractor ?? new EnumExtractor();

        foreach ($this->getAllEnums() as $enumClass) {
            if (! $this->isValidEnum($enumClass)) {
                continue;
            }

            $reflection = new ReflectionClass($enumClass);
            $shortName = $reflection->getShortName();

            // Detect duplicate short names
            if (isset($seen[$shortName])) {
                throw InvalidEnumException::duplicateShortName($shortName, $seen[$shortName], $enumClass);
            }

            $seen[$shortName] = $enumClass;
            $manifest[$shortName] = $extractor->extract($enumClass, $locale);
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
        if ($this->autoDiscovery && config('enumshare.auto_discovery', false)) {
            $discoveredEnums = $this->autoDiscovery->discover();
        }

        $allEnums = array_unique(array_merge($configuredEnums, $discoveredEnums));
        sort($allEnums);

        return $allEnums;
    }

    protected function isValidEnum(string $enumClass): bool
    {
        if ($this->validator) {
            return $this->validator->isValidEnumForExport($enumClass);
        }

        return class_exists($enumClass) && (new ReflectionClass($enumClass))->isEnum();
    }
}
