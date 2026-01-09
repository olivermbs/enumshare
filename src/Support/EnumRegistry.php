<?php

namespace Olivermbs\Enumshare\Support;

use Olivermbs\Enumshare\Exceptions\InvalidEnumException;
use ReflectionClass;

class EnumRegistry
{
    public function __construct(
        protected array $enums = [],
        protected ?EnumAutoDiscovery $autoDiscovery = null
    ) {}

    public function manifest(?string $locale = null): array
    {
        $manifest = [];
        $seen = [];
        $extractor = new EnumExtractor;

        foreach ($this->getAllEnums() as $enumClass) {
            if (! $this->isValidEnum($enumClass)) {
                continue;
            }

            $reflection = new ReflectionClass($enumClass);
            $shortName = $reflection->getShortName();

            if (isset($seen[$shortName])) {
                throw InvalidEnumException::duplicateShortName($shortName, $seen[$shortName], $enumClass);
            }

            $seen[$shortName] = $enumClass;
            $manifest[$shortName] = $extractor->extract($enumClass, $locale);
        }

        ksort($manifest);

        return $manifest;
    }

    public function validateEnums(array $enumClasses): array
    {
        $errors = [];

        foreach ($enumClasses as $enumClass) {
            $error = $this->getValidationError($enumClass);
            if ($error) {
                $errors[$enumClass] = $error;
            }
        }

        return $errors;
    }

    protected function getAllEnums(): array
    {
        $discoveredEnums = [];
        if ($this->autoDiscovery && config('enumshare.auto_discovery', false)) {
            $discoveredEnums = $this->autoDiscovery->discover();
        }

        $allEnums = array_unique(array_merge($this->enums, $discoveredEnums));
        sort($allEnums);

        return $allEnums;
    }

    protected function isValidEnum(string $enumClass): bool
    {
        return $this->getValidationError($enumClass) === null;
    }

    protected function getValidationError(string $enumClass): ?string
    {
        if (! class_exists($enumClass)) {
            return "Enum class '{$enumClass}' does not exist.";
        }

        try {
            $reflection = new ReflectionClass($enumClass);

            if (! $reflection->isEnum()) {
                return "Class '{$enumClass}' is not an enum.";
            }

            if (empty($enumClass::cases())) {
                return "Enum '{$enumClass}' has no cases to export.";
            }
        } catch (\Throwable $e) {
            return "Failed to validate enum '{$enumClass}': {$e->getMessage()}";
        }

        return null;
    }
}
