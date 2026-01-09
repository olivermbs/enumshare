<?php

namespace Olivermbs\Enumshare\Support;

use Olivermbs\Enumshare\Attributes\ExportMethod;
use Olivermbs\Enumshare\Attributes\Label;
use Olivermbs\Enumshare\Attributes\Meta;
use Olivermbs\Enumshare\Attributes\TranslatedLabel;
use ReflectionClassConstant;
use ReflectionEnum;
use ReflectionMethod;

class EnumExtractor
{
    public function extract(string $enumClass, ?string $locale = null): array
    {
        $reflection = new ReflectionEnum($enumClass);
        $enumName = $reflection->getShortName();
        $isBacked = $reflection->isBacked();
        $backingType = $isBacked ? (string) $reflection->getBackingType() : null;
        $effectiveLocale = $locale ?? app()->getLocale();
        $configuredLocales = config('enumshare.locales', []);

        $entries = [];

        foreach ($enumClass::cases() as $case) {
            $caseReflection = $reflection->getReflectionConstant($case->name);

            $entries[] = [
                'key' => $case->name,
                'value' => $isBacked ? $case->value : null,
                'label' => $this->resolveLabel($case, $caseReflection, $enumName, $effectiveLocale, $configuredLocales),
                'meta' => $this->resolveMeta($caseReflection),
                ...$this->resolveCustomMethods($case, $reflection),
            ];
        }

        return [
            'name' => $enumName,
            'fqcn' => $enumClass,
            'backingType' => $backingType,
            'entries' => $entries,
        ];
    }

    protected function resolveLabel($case, ReflectionClassConstant $reflection, string $enumName, string $locale, array $configuredLocales): string|array
    {
        // Check for TranslatedLabel attribute
        if ($attr = $reflection->getAttributes(TranslatedLabel::class)[0] ?? null) {
            $translatedLabel = $attr->newInstance();

            if (empty($configuredLocales)) {
                return trans($translatedLabel->key, $translatedLabel->parameters, $locale);
            }

            $translations = [];
            foreach ($configuredLocales as $localeCode) {
                $translations[$localeCode] = trans($translatedLabel->key, $translatedLabel->parameters, $localeCode);
            }

            return $translations;
        }

        // Check for Label attribute
        if ($attr = $reflection->getAttributes(Label::class)[0] ?? null) {
            return $attr->newInstance()->text;
        }

        // Check translation file
        $langKey = config('enumshare.lang_namespace', 'enums').".{$enumName}.{$case->name}";
        $translation = trans($langKey, [], $locale);

        if ($translation !== $langKey) {
            return $translation;
        }

        // Fallback to case name
        return $case->name;
    }

    protected function resolveMeta(ReflectionClassConstant $reflection): array
    {
        $attrs = $reflection->getAttributes(Meta::class);

        return $attrs ? $attrs[0]->newInstance()->data : [];
    }

    protected function resolveCustomMethods($case, ReflectionEnum $reflection): array
    {
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attr = $method->getAttributes(ExportMethod::class)[0] ?? null;

            if (! $attr || $method->getNumberOfParameters() > 0) {
                continue;
            }

            try {
                $exportMethod = $attr->newInstance();
                $methods[$exportMethod->name ?? $method->getName()] = $case->{$method->getName()}();
            } catch (\Throwable) {
                if (function_exists('logger')) {
                    logger()->warning('Enumshare export method failed', [
                        'enum' => $case::class,
                        'method' => $method->getName(),
                    ]);
                }

                continue;
            }
        }

        return $methods;
    }
}
