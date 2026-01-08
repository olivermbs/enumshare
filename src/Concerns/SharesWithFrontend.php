<?php

namespace Olivermbs\Enumshare\Concerns;

use BackedEnum;
use Olivermbs\Enumshare\Attributes\ExportMethod;
use Olivermbs\Enumshare\Attributes\Label;
use Olivermbs\Enumshare\Attributes\Meta;
use Olivermbs\Enumshare\Attributes\TranslatedLabel;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;

trait SharesWithFrontend
{
    public static function forFrontend(?string $locale = null): array
    {
        $reflection = new ReflectionClass(static::class);
        $enumName = $reflection->getShortName();

        $isBacked = $reflection->implementsInterface(BackedEnum::class);
        $backingType = null;

        if ($isBacked && count(static::cases()) > 0) {
            $firstCase = static::cases()[0];
            $backingType = is_string($firstCase->value) ? 'string' : 'int';
        }

        $configuredLocales = config('enumshare.locales', []);

        $entries = [];
        $options = [];

        foreach (static::cases() as $case) {
            $caseReflection = $reflection->getReflectionConstant($case->name);

            $label = static::resolveLabel($case, $caseReflection, $enumName, $locale, $configuredLocales);
            $meta = static::resolveMeta($caseReflection);

            $entry = [
                'key' => $case->name,
                'value' => $isBacked ? $case->value : null,
                'label' => $label,
                'meta' => $meta,
            ];

            // Add custom method results as properties
            $customProperties = static::resolveCustomMethods($case, $reflection);
            $entry = array_merge($entry, $customProperties);

            $entries[] = $entry;

            // For options, use the label as string (current locale or first available)
            $optionLabel = is_array($label)
                ? ($label[$locale ?? app()->getLocale()] ?? reset($label))
                : $label;

            $options[] = [
                'value' => $isBacked ? $case->value : $case->name,
                'label' => $optionLabel,
            ];
        }

        return [
            'name' => $enumName,
            'fqcn' => static::class,
            'backingType' => $backingType,
            'entries' => $entries,
            'options' => $options,
        ];
    }

    protected static function resolveLabel($case, ReflectionClassConstant $reflection, string $enumName, ?string $locale, array $configuredLocales = []): string|array
    {
        $translatedLabelAttributes = $reflection->getAttributes(TranslatedLabel::class);

        if (! empty($translatedLabelAttributes)) {
            $translatedLabel = $translatedLabelAttributes[0]->newInstance();

            // If no locales configured, use current locale only
            if (empty($configuredLocales)) {
                $currentLocale = $locale ?: app()->getLocale();

                return trans($translatedLabel->key, $translatedLabel->parameters, $currentLocale);
            }

            // Generate translations for all configured locales
            $translations = [];
            foreach ($configuredLocales as $localeCode) {
                $translations[$localeCode] = trans($translatedLabel->key, $translatedLabel->parameters, $localeCode);
            }

            return $translations;
        }

        $labelAttributes = $reflection->getAttributes(Label::class);

        if (! empty($labelAttributes)) {
            return $labelAttributes[0]->newInstance()->text;
        }

        $locale = $locale ?: app()->getLocale();
        $langNamespace = config('enumshare.lang_namespace', 'enums');
        $langKey = "{$langNamespace}.{$enumName}.{$case->name}";

        $translation = trans($langKey, [], $locale);

        if ($translation !== $langKey) {
            return $translation;
        }

        return $case->name;
    }

    protected static function resolveMeta(ReflectionClassConstant $reflection): array
    {
        $metaAttributes = $reflection->getAttributes(Meta::class);

        if (! empty($metaAttributes)) {
            return $metaAttributes[0]->newInstance()->data;
        }

        return [];
    }

    protected static function resolveCustomMethods($case, ReflectionClass $enumReflection): array
    {
        $methods = [];
        $enumMethods = $enumReflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($enumMethods as $method) {
            $exportAttributes = $method->getAttributes(ExportMethod::class);

            if (! empty($exportAttributes)) {
                $exportMethod = $exportAttributes[0]->newInstance();
                $methodName = $exportMethod->name ?? $method->getName();

                // Skip methods with parameters for now (could be extended later)
                if ($method->getNumberOfParameters() > 0) {
                    continue;
                }

                // Call the method on the case and get the result
                try {
                    $result = $case->{$method->getName()}();
                    $methods[$methodName] = $result;
                } catch (\Throwable $e) {
                    // Skip methods that throw exceptions
                    continue;
                }
            }
        }

        return $methods;
    }
}
