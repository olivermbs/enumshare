<?php

namespace Olivermbs\Enumshare\Support;

class TypeScriptTypeResolver
{
    public function phpToTypeScriptType(mixed $value): string
    {
        if (is_null($value)) {
            return 'null';
        }

        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value) || is_float($value)) {
            return 'number';
        }

        if (is_string($value)) {
            return 'string';
        }

        if (is_array($value)) {
            if (empty($value)) {
                return 'Record<string, unknown>';
            }

            if (array_keys($value) === range(0, count($value) - 1)) {
                $firstType = $this->phpToTypeScriptType($value[0]);

                return $firstType.'[]';
            }

            return 'Record<string, any>';
        }

        return 'unknown';
    }

    public function analyzeLabelTypes(array $entries): array
    {
        $labelTypes = [];

        foreach ($entries as $entry) {
            if (is_array($entry['label'])) {
                $labelTypes[] = 'Record<string, string>';
            } else {
                $labelTypes[] = 'string';
            }
        }

        $labelTypes = array_unique($labelTypes);

        return [
            'unionType' => implode(' | ', $labelTypes),
            'isMultilingual' => in_array('Record<string, string>', $labelTypes),
        ];
    }

    public function analyzeMetaTypes(array $entries): array
    {
        $metaTypes = [];

        foreach ($entries as $entry) {
            if (isset($entry['meta']) && is_array($entry['meta'])) {
                foreach ($entry['meta'] as $metaKey => $metaValue) {
                    $tsType = $this->phpToTypeScriptType($metaValue);

                    if (! isset($metaTypes[$metaKey])) {
                        $metaTypes[$metaKey] = [];
                    }

                    $metaTypes[$metaKey][] = $tsType;
                }
            }
        }

        foreach ($metaTypes as $key => $types) {
            $metaTypes[$key] = array_unique($types);
        }

        return $metaTypes;
    }

    public function generateKeyUnionType(array $entries): string
    {
        $keys = collect($entries)->pluck('key')->map(fn ($key) => "'{$key}'")->toArray();

        return implode(' | ', $keys);
    }

    public function determineValueType(?string $backingType, array $entries): string
    {
        if (! $backingType) {
            return 'null';
        }

        $baseType = $backingType === 'string' ? 'string' : 'number';
        $hasNull = in_array(null, collect($entries)->pluck('value')->toArray());

        return $hasNull ? $baseType.' | null' : $baseType;
    }

    public function getOptionValueType(?string $backingType): string
    {
        if (! $backingType) {
            return 'string';
        }

        return $backingType === 'string' ? 'string' : 'number';
    }
}
