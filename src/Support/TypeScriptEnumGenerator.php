<?php

namespace Olivermbs\Enumshare\Support;

class TypeScriptEnumGenerator
{
    public function __construct(
        protected TypeScriptTypeResolver $typeResolver
    ) {}

    public function generate(string $enumName, array $enumData, bool $exportTypes = false, bool $minimal = false): string
    {
        $entries = $enumData['entries'];
        $backingType = $enumData['backingType'];
        $fqcn = $enumData['fqcn'];

        if ($minimal) {
            return $this->buildMinimalTypeScriptFile(
                enumName: $enumName,
                fqcn: $fqcn,
                backingType: $backingType,
                entries: $entries
            );
        }

        $labelAnalysis = $this->typeResolver->analyzeLabelTypes($entries);
        $metaTypes = $this->typeResolver->analyzeMetaTypes($entries);

        return $this->buildTypeScriptFile(
            enumName: $enumName,
            fqcn: $fqcn,
            backingType: $backingType,
            entries: $entries,
            labelAnalysis: $labelAnalysis,
            metaTypes: $metaTypes,
            exportTypes: $exportTypes
        );
    }

    protected function buildMinimalTypeScriptFile(
        string $enumName,
        string $fqcn,
        ?string $backingType,
        array $entries
    ): string {
        $lines = [];
        $lines[] = '/* eslint-disable */';
        $lines[] = "// Auto-generated from {$fqcn}";
        $lines[] = '';

        // Generate value type union
        $valueTypes = [];
        foreach ($entries as $entry) {
            if ($entry['value'] !== null) {
                $valueTypes[] = $backingType === 'string' ? "'{$entry['value']}'" : $entry['value'];
            }
        }
        $valueTypeUnion = implode(' | ', $valueTypes) ?: 'never';
        $lines[] = "export type {$enumName} = {$valueTypeUnion};";
        $lines[] = '';

        // Generate individual constants
        foreach ($entries as $entry) {
            $value = $entry['value'] !== null
                ? ($backingType === 'string' ? "'{$entry['value']}'" : $entry['value'])
                : 'null';
            $lines[] = "export const {$entry['key']}: {$enumName} = {$value};";
        }
        $lines[] = '';

        // Generate grouped object
        $keys = array_map(fn ($e) => $e['key'], $entries);
        $lines[] = "export const {$enumName} = { ".implode(', ', $keys).' } as const;';
        $lines[] = '';

        return implode("\n", $lines);
    }

    protected function buildTypeScriptFile(
        string $enumName,
        string $fqcn,
        ?string $backingType,
        array $entries,
        array $labelAnalysis,
        array $metaTypes,
        bool $exportTypes = false
    ): string {
        $parts = [];
        $export = $exportTypes ? 'export ' : '';

        $parts[] = '/* eslint-disable */';
        $parts[] = '// Auto-generated. Do not edit.';
        $parts[] = '';

        $parts[] = $this->generateMetaType(
            enumName: $enumName,
            metaTypes: $metaTypes,
            export: $export
        );

        $parts[] = $this->generateEntriesConstant(
            enumName: $enumName,
            entries: $entries,
            backingType: $backingType,
            isMultilingual: $labelAnalysis['isMultilingual']
        );
        $parts[] = $this->generateEntryType(
            enumName: $enumName,
            entries: $entries,
            backingType: $backingType,
            labelType: $labelAnalysis['unionType'],
            export: $export
        );
        $parts[] = $this->generateKeyValueTypes(
            enumName: $enumName,
            backingType: $backingType,
            export: $export
        );
        $parts[] = $this->generateOptionType(
            enumName: $enumName,
            backingType: $backingType,
            export: $export
        );
        $parts[] = $this->generateDerivedConstants(
            enumName: $enumName,
            backingType: $backingType,
            entries: $entries
        );
        $parts[] = $this->generateLabelHelper($labelAnalysis['isMultilingual']);
        $parts[] = $this->generateMainEnumObject(
            enumName: $enumName,
            fqcn: $fqcn,
            backingType: $backingType,
            entries: $entries,
            isMultilingual: $labelAnalysis['isMultilingual'],
            exportTypes: $exportTypes
        );

        return implode("\n", $parts);
    }

    protected function generateMetaType(string $enumName, array $metaTypes, string $export = ''): string
    {
        if (empty($metaTypes)) {
            return "{$export}type {$enumName}Meta = Record<string, unknown>;";
        }

        $lines = ["{$export}type {$enumName}Meta = {"];
        foreach ($metaTypes as $prop => $types) {
            $lines[] = "  readonly {$prop}: ".implode(' | ', $types).';';
        }
        $lines[] = '};';

        return implode("\n", $lines);
    }

    protected function generateEntryType(string $enumName, array $entries, ?string $backingType, string $labelType, string $export = ''): string
    {
        $valueType = $backingType ? "{$enumName}Value" : 'null';

        return "{$export}type {$enumName}Entry = Omit<typeof ENTRIES[number], 'label' | 'meta' | 'value'> & {\n".
               "  readonly value: {$valueType};\n".
               "  readonly label: {$labelType};\n".
               "  readonly meta: {$enumName}Meta;\n".
               '};';
    }

    protected function generateOptionType(string $enumName, ?string $backingType, string $export = ''): string
    {
        $valueType = $backingType ? "{$enumName}Value" : "{$enumName}Key";

        return "{$export}type {$enumName}Option = {\n".
               "  readonly value: {$valueType};\n".
               "  readonly label: string;\n".
               '};';
    }

    protected function generateKeyValueTypes(string $enumName, ?string $backingType, string $export = ''): string
    {
        $lines = [];
        $lines[] = "{$export}type {$enumName}Key = typeof ENTRIES[number]['key'];";

        if ($backingType) {
            $lines[] = "{$export}type {$enumName}Value = NonNullable<typeof ENTRIES[number]['value']>;";
        } else {
            $lines[] = "{$export}type {$enumName}Value = {$enumName}Key;";
        }

        return implode("\n", $lines);
    }

    protected function generateEntriesConstant(string $enumName, array $entries, ?string $backingType, bool $isMultilingual): string
    {
        $lines = ['const ENTRIES = ['];

        foreach ($entries as $entry) {
            $lines[] = '  {';
            $lines[] = "    key: '{$entry['key']}',";

            if ($entry['value'] !== null) {
                $value = $backingType === 'string' ? "'{$entry['value']}'" : $entry['value'];
                $lines[] = "    value: {$value},";
            } else {
                $lines[] = '    value: null,';
            }

            if ($isMultilingual) {
                $lines[] = '    label: '.json_encode($entry['label']).',';
            } else {
                $lines[] = "    label: '".addslashes($entry['label'])."',";
            }

            $lines[] = '    meta: '.json_encode($entry['meta']).',';
            $lines[] = '  },';
        }

        $lines[] = '] as const;';

        return implode("\n", $lines);
    }

    protected function generateDerivedConstants(string $enumName, ?string $backingType, array $entries): string
    {
        $lines = [];
        $lines[] = '';
        $lines[] = '// Derived constants';
        $lines[] = "const KEYS: readonly {$enumName}Key[] = ENTRIES.map(e => e.key);";

        if ($backingType) {
            $lines[] = "const VALUES: readonly {$enumName}Value[] = ENTRIES.map(e => e.value).filter((v): v is {$enumName}Value => v !== null);";
            $lines[] = "const OPTIONS: ReadonlyArray<{$enumName}Option> = ENTRIES.filter((e): e is typeof ENTRIES[number] & { value: {$enumName}Value } => e.value !== null).map(e => ({ value: e.value, label: e.label }));";
        } else {
            $lines[] = "const VALUES: readonly {$enumName}Key[] = KEYS;";
            $lines[] = "const OPTIONS: ReadonlyArray<{$enumName}Option> = ENTRIES.map(e => ({ value: e.key, label: e.label }));";
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

    protected function generateMainEnumObject(string $enumName, string $fqcn, ?string $backingType, array $entries, bool $isMultilingual, bool $exportTypes = false): string
    {
        $lines = [];

        $lines[] = "/** {$enumName} - Generated from {$fqcn} */";

        $lines[] = '';
        $lines[] = "export const {$enumName} = {";
        $lines[] = "  name: '{$enumName}' as const,";
        $lines[] = "  fqcn: '".addslashes($fqcn)."' as const,";
        if ($backingType) {
            $lines[] = "  backingType: '{$backingType}' as const,";
        } else {
            $lines[] = '  backingType: null,';
        }

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

        $lines[] = $this->generateUtilityMethodImplementations($enumName, $backingType, $isMultilingual);

        $lines[] = '}';

        return implode("\n", $lines);
    }

    protected function generateLabelHelper(bool $isMultilingual): string
    {
        if (! $isMultilingual) {
            return '';
        }

        return implode("\n", [
            '',
            '// Locale-aware label resolution helper',
            'function resolveLabel(label: string | Record<string, string>, locale?: string): string {',
            '  if (typeof label === \'string\') return label;',
            '  if (locale && label[locale]) return label[locale];',
            '  if (label.en) return label.en;',
            '  const firstKey = Object.keys(label)[0];',
            '  return firstKey ? label[firstKey] : \'\';',
            '}',
        ]);
    }

    protected function generateUtilityMethodImplementations(string $enumName, ?string $backingType, bool $isMultilingual): string
    {
        $lines = [];

        $lines[] = '';
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

        $lines[] = '  fromKey(key: string | null | undefined): typeof ENTRIES[number] | null {';
        $lines[] = '    if (key == null) return null;';
        $lines[] = "    return BY_KEY.get(key as {$enumName}Key) ?? null;";
        $lines[] = '  },';

        if ($backingType) {
            $lines[] = "  isValid(value: unknown): value is {$enumName}Value {";
            $lines[] = '    return typeof value === \''.($backingType === 'string' ? 'string' : 'number').'\' && BY_VALUE.has(value as '.$enumName.'Value);';
        } else {
            $lines[] = "  isValid(value: unknown): value is {$enumName}Key {";
            $lines[] = '    return typeof value === \'string\' && BY_KEY.has(value as '.$enumName.'Key);';
        }
        $lines[] = '  },';

        $lines[] = "  hasKey(key: unknown): key is {$enumName}Key {";
        $lines[] = '    return typeof key === \'string\' && BY_KEY.has(key as '.$enumName.'Key);';
        $lines[] = '  },';

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
