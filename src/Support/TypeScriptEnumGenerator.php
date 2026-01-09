<?php

namespace Olivermbs\Enumshare\Support;

class TypeScriptEnumGenerator
{
    public function __construct(
        protected TypeScriptTypeResolver $typeResolver
    ) {}

    public function generate(string $enumName, array $enumData, bool $exportTypes = false, string $mode = 'full'): string
    {
        $entries = $enumData['entries'];
        $backingType = $enumData['backingType'];
        $fqcn = $enumData['fqcn'];

        return $mode === 'minimal'
            ? $this->generateMinimal($enumName, $fqcn, $backingType, $entries)
            : $this->generateFull($enumName, $fqcn, $backingType, $entries, $exportTypes);
    }

    protected function generateMinimal(string $enumName, string $fqcn, ?string $backingType, array $entries): string
    {
        $lines = [];
        $lines[] = '/* eslint-disable */';
        $lines[] = "// Auto-generated from {$fqcn}";
        $lines[] = '';

        // Build the const object
        $lines[] = "export const {$enumName} = {";
        foreach ($entries as $entry) {
            $value = $backingType
                ? $this->formatValue($entry['value'], $backingType)
                : "'" . addslashes($entry['key']) . "'";
            $lines[] = "  {$entry['key']}: {$value},";
        }
        $lines[] = '} as const;';
        $lines[] = '';

        // Type derived from the const
        $lines[] = "export type {$enumName} = typeof {$enumName}[keyof typeof {$enumName}];";
        $lines[] = '';

        return implode("\n", $lines);
    }

    protected function generateFull(string $enumName, string $fqcn, ?string $backingType, array $entries, bool $exportTypes): string
    {
        $labelAnalysis = $this->typeResolver->analyzeLabelTypes($entries);
        $metaTypes = $this->typeResolver->analyzeMetaTypes($entries);
        $export = $exportTypes ? 'export ' : '';
        $isMultilingual = $labelAnalysis['isMultilingual'];

        $lines = [];
        $lines[] = '/* eslint-disable */';
        $lines[] = '// Auto-generated. Do not edit.';
        $lines[] = '';

        // Meta type
        $lines[] = $this->buildMetaType($enumName, $metaTypes, $export);

        // Entries constant
        $lines[] = $this->buildEntriesConstant($entries, $backingType, $isMultilingual);

        // Types
        $lines[] = $this->buildTypes($enumName, $backingType, $labelAnalysis['unionType'], $export);

        // Derived constants
        $lines[] = $this->buildDerivedConstants($enumName, $backingType, $entries, $isMultilingual);

        // Label helper for multilingual
        if ($isMultilingual) {
            $lines[] = $this->buildLabelHelper();
        }

        // Main enum object
        $lines[] = $this->buildMainObject($enumName, $fqcn, $backingType, $entries, $isMultilingual);

        return implode("\n", $lines);
    }

    protected function formatValue(mixed $value, ?string $backingType): string
    {
        if ($value === null) {
            return 'null';
        }

        if ($backingType === 'string') {
            $escaped = addslashes((string) $value);

            return "'{$escaped}'";
        }

        return (string) $value;
    }

    protected function buildMetaType(string $enumName, array $metaTypes, string $export): string
    {
        if (empty($metaTypes)) {
            return "{$export}type {$enumName}Meta = Record<string, unknown>;";
        }

        $lines = ["{$export}type {$enumName}Meta = {"];
        foreach ($metaTypes as $prop => $types) {
            $lines[] = "  readonly {$prop}: " . implode(' | ', $types) . ';';
        }
        $lines[] = '};';

        return implode("\n", $lines);
    }

    protected function buildEntriesConstant(array $entries, ?string $backingType, bool $isMultilingual): string
    {
        $lines = ['const ENTRIES = ['];

        foreach ($entries as $entry) {
            $lines[] = '  {';
            $lines[] = "    key: '{$entry['key']}',";
            $lines[] = '    value: ' . $this->formatValue($entry['value'], $backingType) . ',';

            if ($isMultilingual) {
                $lines[] = '    label: ' . json_encode($entry['label']) . ',';
            } else {
                $lines[] = "    label: '" . addslashes($entry['label']) . "',";
            }

            $lines[] = '    meta: ' . json_encode($entry['meta']) . ',';
            $lines[] = '  },';
        }

        $lines[] = '] as const;';

        return implode("\n", $lines);
    }

    protected function buildTypes(string $enumName, ?string $backingType, string $labelType, string $export): string
    {
        $valueType = $backingType ? "{$enumName}Value" : 'null';

        $lines = [];
        $lines[] = "{$export}type {$enumName}Entry = Omit<typeof ENTRIES[number], 'label' | 'meta' | 'value'> & {";
        $lines[] = "  readonly value: {$valueType};";
        $lines[] = "  readonly label: {$labelType};";
        $lines[] = "  readonly meta: {$enumName}Meta;";
        $lines[] = '};';

        $lines[] = "{$export}type {$enumName}Key = typeof ENTRIES[number]['key'];";

        if ($backingType) {
            $lines[] = "{$export}type {$enumName}Value = NonNullable<typeof ENTRIES[number]['value']>;";
        } else {
            $lines[] = "{$export}type {$enumName}Value = {$enumName}Key;";
        }

        $optionValueType = $backingType ? "{$enumName}Value" : "{$enumName}Key";
        $lines[] = "{$export}type {$enumName}Option = {";
        $lines[] = "  readonly value: {$optionValueType};";
        $lines[] = '  readonly label: string;';
        $lines[] = '};';

        return implode("\n", $lines);
    }

    protected function buildDerivedConstants(string $enumName, ?string $backingType, array $entries, bool $isMultilingual): string
    {
        $lines = [];
        $lines[] = '';
        $lines[] = '// Derived constants';
        $lines[] = "const KEYS: readonly {$enumName}Key[] = ENTRIES.map(e => e.key);";

        if ($backingType) {
            $lines[] = "const VALUES: readonly {$enumName}Value[] = ENTRIES.map(e => e.value).filter((v): v is {$enumName}Value => v !== null);";
            $labelValue = $isMultilingual ? 'resolveLabel(e.label)' : 'e.label';
            $lines[] = "const OPTIONS: ReadonlyArray<{$enumName}Option> = ENTRIES.filter((e): e is typeof ENTRIES[number] & { value: {$enumName}Value } => e.value !== null).map(e => ({ value: e.value, label: {$labelValue} }));";
        } else {
            $lines[] = "const VALUES: readonly {$enumName}Key[] = KEYS;";
            $labelValue = $isMultilingual ? 'resolveLabel(e.label)' : 'e.label';
            $lines[] = "const OPTIONS: ReadonlyArray<{$enumName}Option> = ENTRIES.map(e => ({ value: e.key, label: {$labelValue} }));";
        }

        $lines[] = '';
        $lines[] = '// Lookup maps for O(1) access';
        $lines[] = "const BY_KEY = new Map<{$enumName}Key, typeof ENTRIES[number]>(ENTRIES.map(e => [e.key, e]));";

        if ($backingType) {
            $lines[] = "const BY_VALUE = new Map<{$enumName}Value, typeof ENTRIES[number]>(ENTRIES.filter(e => e.value !== null).map(e => [e.value as {$enumName}Value, e]));";
        }

        $lines[] = '';
        $lines[] = '// Individual entry constants';
        foreach ($entries as $index => $entry) {
            $lines[] = "const {$entry['key']}: typeof ENTRIES[number] = ENTRIES[{$index}];";
        }

        return implode("\n", $lines);
    }

    protected function buildLabelHelper(): string
    {
        return implode("\n", [
            '',
            '// Locale-aware label resolution helper',
            'function resolveLabel(label: string | Record<string, string>, locale?: string): string {',
            "  if (typeof label === 'string') return label;",
            '  if (locale && label[locale]) return label[locale];',
            '  if (label.en) return label.en;',
            '  const firstKey = Object.keys(label)[0];',
            "  return firstKey ? label[firstKey] : '';",
            '}',
        ]);
    }

    protected function buildMainObject(string $enumName, string $fqcn, ?string $backingType, array $entries, bool $isMultilingual): string
    {
        $lines = [];
        $lines[] = '';
        $lines[] = "/** {$enumName} - Generated from {$fqcn} */";
        $lines[] = '';
        $lines[] = "export const {$enumName} = {";
        $lines[] = "  name: '{$enumName}' as const,";
        $lines[] = "  fqcn: '" . addslashes($fqcn) . "' as const,";
        $lines[] = $backingType ? "  backingType: '{$backingType}' as const," : '  backingType: null,';
        $lines[] = '';

        foreach ($entries as $entry) {
            $lines[] = "  {$entry['key']},";
        }

        $lines[] = '';
        $lines[] = '  // Collections (derived from ENTRIES)';
        $lines[] = '  entries: ENTRIES,';
        $lines[] = '  keys: KEYS,';
        $lines[] = '  values: VALUES,';
        $lines[] = '  options: OPTIONS,';
        $lines[] = '  count: ENTRIES.length,';

        $lines[] = $this->buildUtilityMethods($enumName, $backingType, $isMultilingual);

        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    protected function buildUtilityMethods(string $enumName, ?string $backingType, bool $isMultilingual): string
    {
        $lines = [];
        $lines[] = '';

        // from() method
        if ($backingType) {
            $valueType = $backingType === 'string' ? 'string' : 'number';
            $lines[] = "  from(value: {$valueType} | null | undefined): typeof ENTRIES[number] | null {";
            $lines[] = '    if (value == null) return null;';
            $lines[] = "    return BY_VALUE.get(value as {$enumName}Value) ?? null;";
        } else {
            $lines[] = '  from(value: string | null | undefined): typeof ENTRIES[number] | null {';
            $lines[] = '    if (value == null) return null;';
            $lines[] = "    return BY_KEY.get(value as {$enumName}Key) ?? null;";
        }
        $lines[] = '  },';

        // fromKey() method
        $lines[] = '  fromKey(key: string | null | undefined): typeof ENTRIES[number] | null {';
        $lines[] = '    if (key == null) return null;';
        $lines[] = "    return BY_KEY.get(key as {$enumName}Key) ?? null;";
        $lines[] = '  },';

        // isValid() method
        if ($backingType) {
            $typeCheck = $backingType === 'string' ? 'string' : 'number';
            $lines[] = "  isValid(value: unknown): value is {$enumName}Value {";
            $lines[] = "    return typeof value === '{$typeCheck}' && BY_VALUE.has(value as {$enumName}Value);";
        } else {
            $lines[] = "  isValid(value: unknown): value is {$enumName}Key {";
            $lines[] = "    return typeof value === 'string' && BY_KEY.has(value as {$enumName}Key);";
        }
        $lines[] = '  },';

        // hasKey() method
        $lines[] = "  hasKey(key: unknown): key is {$enumName}Key {";
        $lines[] = "    return typeof key === 'string' && BY_KEY.has(key as {$enumName}Key);";
        $lines[] = '  },';

        // labels() method
        if ($isMultilingual) {
            $lines[] = '  labels(locale?: string): readonly string[] {';
            $lines[] = '    return ENTRIES.map(e => resolveLabel(e.label, locale));';
            $lines[] = '  },';
        } else {
            $lines[] = '  labels(): readonly string[] { return ENTRIES.map(e => e.label); },';
        }

        return implode("\n", $lines);
    }
}
