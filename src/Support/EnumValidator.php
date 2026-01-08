<?php

namespace Olivermbs\Enumshare\Support;

use Olivermbs\Enumshare\Concerns\SharesWithFrontend;
use Olivermbs\Enumshare\Exceptions\InvalidEnumException;
use ReflectionClass;
use ReflectionException;

class EnumValidator
{
    public function validateEnumForExport(string $enumClass): void
    {
        $this->validateClassExists($enumClass);
        $this->validateIsEnum($enumClass);
        $this->validateUsesSharesWithFrontendTrait($enumClass);
        $this->validateHasCases($enumClass);
    }

    public function validateMultipleEnumsForExport(array $enumClasses): array
    {
        $validEnums = [];
        $errors = [];

        foreach ($enumClasses as $enumClass) {
            try {
                $this->validateEnumForExport($enumClass);
                $validEnums[] = $enumClass;
            } catch (InvalidEnumException $e) {
                $errors[$enumClass] = $e->getMessage();
            }
        }

        return [
            'valid' => $validEnums,
            'errors' => $errors,
        ];
    }

    public function isValidEnumForExport(string $enumClass): bool
    {
        try {
            $this->validateEnumForExport($enumClass);

            return true;
        } catch (InvalidEnumException) {
            return false;
        }
    }

    protected function validateClassExists(string $enumClass): void
    {
        if (! class_exists($enumClass)) {
            throw new InvalidEnumException("Enum class '{$enumClass}' does not exist.");
        }
    }

    protected function validateIsEnum(string $enumClass): void
    {
        try {
            $reflection = new ReflectionClass($enumClass);

            if (! $reflection->isEnum()) {
                throw new InvalidEnumException("Class '{$enumClass}' is not an enum.");
            }
        } catch (ReflectionException $e) {
            throw new InvalidEnumException("Failed to reflect enum class '{$enumClass}': {$e->getMessage()}");
        }
    }

    protected function validateUsesSharesWithFrontendTrait(string $enumClass): void
    {
        try {
            $reflection = new ReflectionClass($enumClass);
            $traits = $reflection->getTraitNames();

            if (! in_array(SharesWithFrontend::class, $traits)) {
                throw new InvalidEnumException(
                    "Enum '{$enumClass}' must use the SharesWithFrontend trait to be exported."
                );
            }
        } catch (ReflectionException $e) {
            throw new InvalidEnumException("Failed to validate traits for enum '{$enumClass}': {$e->getMessage()}");
        }
    }

    protected function validateHasCases(string $enumClass): void
    {
        try {
            $cases = $enumClass::cases();

            if (empty($cases)) {
                throw new InvalidEnumException("Enum '{$enumClass}' has no cases to export.");
            }
        } catch (\Error $e) {
            throw new InvalidEnumException("Failed to get cases for enum '{$enumClass}': {$e->getMessage()}");
        }
    }
}
