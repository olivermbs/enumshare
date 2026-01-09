<?php

namespace Olivermbs\Enumshare\Support;

class TypeScriptEnumGenerator
{
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
        $values = $this->buildMinimalValues($entries, $backingType);

        return $this->dedent(<<<TS
        /* eslint-disable */
        // Auto-generated from {$fqcn}

        export const {$enumName} = {
        {$values}
        } as const;

        export type {$enumName} = typeof {$enumName}[keyof typeof {$enumName}];
        TS);
    }

    protected function generateFull(string $enumName, string $fqcn, ?string $backingType, array $entries, bool $exportTypes): string
    {
        $isMultilingual = $this->hasMultilingualLabels($entries);
        $metaType = $this->buildMetaType($enumName, $entries, $exportTypes);
        $entriesConst = $this->buildEntriesConstant($entries, $backingType, $isMultilingual);
        $types = $this->buildTypes($enumName, $backingType, $isMultilingual, $exportTypes);
        $derived = $this->buildDerivedConstants($enumName, $backingType, $isMultilingual);
        $entryConsts = $this->buildEntryConstants($entries);
        $labelHelper = $isMultilingual ? $this->buildLabelHelper() : '';
        $entryRefs = $this->buildEntryReferences($entries);
        $methods = $this->buildUtilityMethods($enumName, $backingType, $isMultilingual);

        return $this->dedent(<<<TS
        /* eslint-disable */
        // Auto-generated from {$fqcn}

        {$metaType}
        {$entriesConst}
        {$types}
        {$derived}
        {$entryConsts}
        {$labelHelper}
        export const {$enumName} = {
        {$entryRefs}
          entries: ENTRIES,
          keys: KEYS,
          values: VALUES,
          options: OPTIONS,
          count: ENTRIES.length,
        {$methods}
        }
        TS);
    }

    protected function dedent(string $text): string
    {
        $lines = explode("\n", $text);
        $minIndent = PHP_INT_MAX;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $indent = strlen($line) - strlen(ltrim($line));
            $minIndent = min($minIndent, $indent);
        }

        if ($minIndent === PHP_INT_MAX || $minIndent === 0) {
            return $text;
        }

        $result = array_map(
            fn ($line) => trim($line) === '' ? '' : substr($line, $minIndent),
            $lines
        );

        return implode("\n", $result);
    }

    protected function buildMinimalValues(array $entries, ?string $backingType): string
    {
        $lines = [];
        foreach ($entries as $entry) {
            $value = $backingType
                ? $this->formatValue($entry['value'], $backingType)
                : "'".addslashes($entry['key'])."'";
            $lines[] = "  {$entry['key']}: {$value},";
        }

        return implode("\n", $lines);
    }

    protected function formatValue(mixed $value, ?string $backingType): string
    {
        if ($value === null) {
            return 'null';
        }

        if ($backingType === 'string') {
            return "'".addslashes((string) $value)."'";
        }

        return (string) $value;
    }

    protected function hasMultilingualLabels(array $entries): bool
    {
        foreach ($entries as $entry) {
            if (is_array($entry['label'])) {
                return true;
            }
        }

        return false;
    }

    protected function buildMetaType(string $enumName, array $entries, bool $exportTypes): string
    {
        $export = $exportTypes ? 'export ' : '';
        $metaTypes = [];

        foreach ($entries as $entry) {
            if (! is_array($entry['meta']) || empty($entry['meta'])) {
                continue;
            }
            foreach ($entry['meta'] as $key => $value) {
                $metaTypes[$key][] = $this->phpToTsType($value);
            }
        }

        if (empty($metaTypes)) {
            return "{$export}type {$enumName}Meta = Record<string, unknown>;";
        }

        $props = [];
        foreach ($metaTypes as $key => $types) {
            $union = implode(' | ', array_unique($types));
            $props[] = "  readonly {$key}: {$union};";
        }
        $propsStr = implode("\n", $props);

        return "{$export}type {$enumName}Meta = {\n{$propsStr}\n};";
    }

    protected function phpToTsType(mixed $value): string
    {
        return match (true) {
            is_null($value) => 'null',
            is_bool($value) => 'boolean',
            is_int($value), is_float($value) => 'number',
            is_string($value) => 'string',
            is_array($value) && empty($value) => 'Record<string, unknown>',
            is_array($value) && array_is_list($value) => $this->phpToTsType($value[0]).'[]',
            is_array($value) => 'Record<string, unknown>',
            default => 'unknown',
        };
    }

    protected function buildEntriesConstant(array $entries, ?string $backingType, bool $isMultilingual): string
    {
        $items = [];
        foreach ($entries as $entry) {
            $value = $this->formatValue($entry['value'], $backingType);
            $label = $isMultilingual
                ? json_encode($entry['label'])
                : "'".addslashes($entry['label'])."'";
            $meta = empty($entry['meta']) ? '{}' : json_encode($entry['meta']);

            $items[] = "  { key: '{$entry['key']}', value: {$value}, label: {$label}, meta: {$meta} },";
        }

        return "const ENTRIES = [\n".implode("\n", $items)."\n] as const;";
    }

    protected function buildTypes(string $enumName, ?string $backingType, bool $isMultilingual, bool $exportTypes): string
    {
        $export = $exportTypes ? 'export ' : '';
        $labelType = $isMultilingual ? 'string | Record<string, string>' : 'string';
        $valueType = $backingType ? "{$enumName}Value" : "{$enumName}Key";

        $valueTypeDef = $backingType
            ? "{$export}type {$enumName}Value = NonNullable<typeof ENTRIES[number]['value']>;"
            : "{$export}type {$enumName}Value = {$enumName}Key;";

        $entryValueType = $backingType ? "{$enumName}Value" : 'null';

        return <<<TS
        {$export}type {$enumName}Entry = {
          readonly key: {$enumName}Key;
          readonly value: {$entryValueType};
          readonly label: {$labelType};
          readonly meta: {$enumName}Meta;
        };
        {$export}type {$enumName}Key = typeof ENTRIES[number]['key'];
        {$valueTypeDef}
        {$export}type {$enumName}Option = { readonly value: {$valueType}; readonly label: string; };
        TS;
    }

    protected function buildDerivedConstants(string $enumName, ?string $backingType, bool $isMultilingual): string
    {
        $labelExpr = $isMultilingual ? 'resolveLabel(e.label)' : 'e.label';

        if ($backingType) {
            return <<<TS
            const KEYS: readonly {$enumName}Key[] = ENTRIES.map(e => e.key);
            const VALUES: readonly {$enumName}Value[] = ENTRIES.map(e => e.value!);
            const OPTIONS: readonly {$enumName}Option[] = ENTRIES.map(e => ({ value: e.value!, label: {$labelExpr} }));
            const BY_KEY = new Map(ENTRIES.map(e => [e.key, e]));
            const BY_VALUE = new Map(ENTRIES.map(e => [e.value, e]));
            TS;
        }

        return <<<TS
        const KEYS: readonly {$enumName}Key[] = ENTRIES.map(e => e.key);
        const VALUES: readonly {$enumName}Key[] = KEYS;
        const OPTIONS: readonly {$enumName}Option[] = ENTRIES.map(e => ({ value: e.key, label: {$labelExpr} }));
        const BY_KEY = new Map(ENTRIES.map(e => [e.key, e]));
        TS;
    }

    protected function buildEntryConstants(array $entries): string
    {
        $lines = [];
        foreach ($entries as $i => $entry) {
            $lines[] = "const {$entry['key']} = ENTRIES[{$i}];";
        }

        return implode("\n", $lines);
    }

    protected function buildLabelHelper(): string
    {
        return <<<'TS'
        function resolveLabel(label: string | Record<string, string>, locale?: string): string {
          if (typeof label === 'string') return label;
          return (locale && label[locale]) || label.en || Object.values(label)[0] || '';
        }
        TS;
    }

    protected function buildEntryReferences(array $entries): string
    {
        $refs = [];
        foreach ($entries as $entry) {
            $refs[] = "  {$entry['key']},";
        }

        return implode("\n", $refs);
    }

    protected function buildUtilityMethods(string $enumName, ?string $backingType, bool $isMultilingual): string
    {
        $valueType = $backingType === 'string' ? 'string' : ($backingType ? 'number' : 'string');
        $typeCheck = $backingType === 'string' ? 'string' : ($backingType ? 'number' : 'string');
        $entryType = 'typeof ENTRIES[number]';

        $fromMethod = $backingType
            ? "from(value: {$valueType} | null | undefined): {$entryType} | null { return value == null ? null : BY_VALUE.get(value) ?? null; },"
            : "from(value: string | null | undefined): {$entryType} | null { return value == null ? null : BY_KEY.get(value as {$enumName}Key) ?? null; },";

        $isValidMethod = $backingType
            ? "isValid(value: unknown): value is {$enumName}Value { return typeof value === '{$typeCheck}' && BY_VALUE.has(value as {$enumName}Value); },"
            : "isValid(value: unknown): value is {$enumName}Key { return typeof value === 'string' && BY_KEY.has(value as {$enumName}Key); },";

        $labelsMethod = $isMultilingual
            ? 'labels(locale?: string): readonly string[] { return ENTRIES.map(e => resolveLabel(e.label, locale)); },'
            : 'labels(): readonly string[] { return ENTRIES.map(e => e.label); },';

        return <<<TS
          {$fromMethod}
          fromKey(key: string | null | undefined): {$entryType} | null { return key == null ? null : BY_KEY.get(key as {$enumName}Key) ?? null; },
          {$isValidMethod}
          hasKey(key: unknown): key is {$enumName}Key { return typeof key === 'string' && BY_KEY.has(key as {$enumName}Key); },
          {$labelsMethod}
        TS;
    }
}
