<?php

namespace Olivermbs\Enumshare\Support;

use Olivermbs\Enumshare\Attributes\DontExport;
use Olivermbs\Enumshare\Exceptions\InvalidEnumException;
use ReflectionClass;

class EnumRegistry
{
    protected const array GENERATED_IDENTIFIERS = [
        'entries',
        'keys',
        'values',
        'options',
        'count',
        'from',
        'fromKey',
        'isValid',
        'hasKey',
        'labels',
        'ENTRIES',
        'KEYS',
        'VALUES',
        'OPTIONS',
        'BY_KEY',
        'BY_VALUE',
        'resolveLabel',
    ];

    protected const array MODULE_LEVEL_GENERATED_IDENTIFIERS = [
        'ENTRIES',
        'KEYS',
        'VALUES',
        'OPTIONS',
        'BY_KEY',
        'BY_VALUE',
        'resolveLabel',
    ];

    protected const array TYPESCRIPT_RESERVED_WORDS = [
        'arguments',
        'await',
        'break',
        'case',
        'catch',
        'class',
        'const',
        'continue',
        'debugger',
        'default',
        'delete',
        'do',
        'else',
        'enum',
        'eval',
        'export',
        'extends',
        'false',
        'finally',
        'for',
        'function',
        'if',
        'implements',
        'import',
        'in',
        'instanceof',
        'interface',
        'let',
        'new',
        'null',
        'package',
        'private',
        'protected',
        'public',
        'return',
        'static',
        'super',
        'switch',
        'this',
        'throw',
        'true',
        'try',
        'typeof',
        'var',
        'void',
        'while',
        'with',
        'yield',
    ];

    protected EnumExtractor $extractor;

    public function __construct(
        protected array $enums = [],
        protected ?EnumAutoDiscovery $autoDiscovery = null,
        ?EnumExtractor $extractor = null
    ) {
        $this->extractor = $extractor ?? new EnumExtractor;
    }

    public function manifest(?string $locale = null): array
    {
        $manifest = [];
        $seen = [];

        foreach ($this->exportableEnumClasses() as $enumClass) {
            $reflection = new ReflectionClass($enumClass);
            $shortName = $reflection->getShortName();

            if (isset($seen[$shortName])) {
                throw InvalidEnumException::duplicateShortName($shortName, $seen[$shortName], $enumClass);
            }

            $seen[$shortName] = $enumClass;
            $manifest[$shortName] = $this->extractor->extract($enumClass, $locale);
        }

        ksort($manifest);

        return $manifest;
    }

    public function validateEnums(array $enumClasses): array
    {
        $errors = [];

        foreach ($enumClasses as $enumClass) {
            if ($this->isMarkedDontExport($enumClass)) {
                continue;
            }

            $error = $this->getValidationError($enumClass);
            if ($error) {
                $errors[$enumClass] = $error;
            }
        }

        return $errors;
    }

    public function exportableEnumClasses(): array
    {
        $discoveredEnums = [];
        if ($this->autoDiscovery && config('enumshare.auto_discovery', false)) {
            $discoveredEnums = $this->autoDiscovery->discover();
        }

        $allEnums = array_unique(array_merge($this->enums, $discoveredEnums));
        $allEnums = array_values(array_filter(
            $allEnums,
            fn (string $enumClass) => ! $this->isMarkedDontExport($enumClass) && $this->isValidEnum($enumClass)
        ));
        sort($allEnums);

        return $allEnums;
    }

    protected function isMarkedDontExport(string $enumClass): bool
    {
        if (! class_exists($enumClass)) {
            return false;
        }

        return (new ReflectionClass($enumClass))->getAttributes(DontExport::class) !== [];
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

            $shortName = $reflection->getShortName();

            if (in_array($shortName, self::MODULE_LEVEL_GENERATED_IDENTIFIERS, true)) {
                return "Enum '{$enumClass}' short name '{$shortName}' conflicts with a generated TypeScript identifier.";
            }

            if (in_array($shortName, self::TYPESCRIPT_RESERVED_WORDS, true)) {
                return "Enum '{$enumClass}' short name '{$shortName}' is a reserved JavaScript/TypeScript word.";
            }

            foreach ($enumClass::cases() as $case) {
                if ($case->name === $shortName) {
                    return "Enum '{$enumClass}' case '{$case->name}' matches its short name and would create a duplicate TypeScript identifier.";
                }

                if (in_array($case->name, self::GENERATED_IDENTIFIERS, true)) {
                    return "Enum '{$enumClass}' case '{$case->name}' conflicts with a generated TypeScript identifier.";
                }

                if (in_array($case->name, self::TYPESCRIPT_RESERVED_WORDS, true)) {
                    return "Enum '{$enumClass}' case '{$case->name}' is a reserved JavaScript/TypeScript word.";
                }
            }
        } catch (\Throwable $e) {
            return "Failed to validate enum '{$enumClass}': {$e->getMessage()}";
        }

        return null;
    }
}
