<?php

use Olivermbs\Enumshare\Support\TypeScriptEnumGenerator;
use Olivermbs\Enumshare\Support\TypeScriptTypeResolver;

beforeEach(function () {
    $this->typeResolver = new TypeScriptTypeResolver;
    $this->generator = new TypeScriptEnumGenerator($this->typeResolver);
});

it('generates TypeScript for simple string enum', function () {
    $enumData = [
        'fqcn' => 'App\Enums\Status',
        'backingType' => 'string',
        'entries' => [
            [
                'key' => 'Active',
                'value' => 'active',
                'label' => 'Active Status',
                'meta' => ['color' => 'green'],
            ],
            [
                'key' => 'Inactive',
                'value' => 'inactive',
                'label' => 'Inactive Status',
                'meta' => ['color' => 'red'],
            ],
        ],
        'options' => [
            ['value' => 'active', 'label' => 'Active Status'],
            ['value' => 'inactive', 'label' => 'Inactive Status'],
        ],
    ];

    $result = $this->generator->generate(enumName: 'Status', enumData: $enumData, exportTypes: true);

    expect($result)->toContain('export type StatusMeta');
    expect($result)->toContain('export type StatusKey');
    expect($result)->toContain('export type StatusValue');
    expect($result)->toContain('export const Status = {');
    expect($result)->toContain("name: 'Status' as const,");
    expect($result)->toContain("fqcn: 'App\\\\Enums\\\\Status' as const,");
    expect($result)->toContain("backingType: 'string' as const");
});

it('generates TypeScript for integer enum', function () {
    $enumData = [
        'fqcn' => 'App\Enums\Priority',
        'backingType' => 'int',
        'entries' => [
            [
                'key' => 'Low',
                'value' => 1,
                'label' => 'Low Priority',
                'meta' => [],
            ],
            [
                'key' => 'High',
                'value' => 10,
                'label' => 'High Priority',
                'meta' => [],
            ],
        ],
        'options' => [
            ['value' => 1, 'label' => 'Low Priority'],
            ['value' => 10, 'label' => 'High Priority'],
        ],
    ];

    $result = $this->generator->generate(enumName: 'Priority', enumData: $enumData, exportTypes: true);

    expect($result)->toContain('readonly value: PriorityValue;');
    expect($result)->toContain("backingType: 'int' as const");
    expect($result)->toContain('value: 1,');
    expect($result)->toContain('value: 10,');
});

it('generates TypeScript for pure enum without backing type', function () {
    $enumData = [
        'fqcn' => 'App\Enums\Direction',
        'backingType' => null,
        'entries' => [
            [
                'key' => 'North',
                'value' => null,
                'label' => 'North Direction',
                'meta' => [],
            ],
            [
                'key' => 'South',
                'value' => null,
                'label' => 'South Direction',
                'meta' => [],
            ],
        ],
        'options' => [
            ['value' => 'North', 'label' => 'North Direction'],
            ['value' => 'South', 'label' => 'South Direction'],
        ],
    ];

    $result = $this->generator->generate(enumName: 'Direction', enumData: $enumData, exportTypes: true);

    expect($result)->toContain('readonly value: null;');
    expect($result)->toContain('backingType: null,');
    expect($result)->toContain('value: null,');
});

it('generates TypeScript for multilingual enum', function () {
    $enumData = [
        'fqcn' => 'App\Enums\Status',
        'backingType' => 'string',
        'entries' => [
            [
                'key' => 'Active',
                'value' => 'active',
                'label' => ['en' => 'Active', 'es' => 'Activo'],
                'meta' => [],
            ],
        ],
        'options' => [
            ['value' => 'active', 'label' => 'Active'],
        ],
    ];

    $result = $this->generator->generate('Status', $enumData);

    expect($result)->toContain('readonly label: Record<string, string>;');
    expect($result)->toContain('labels(locale?: string): readonly string[]');
    expect($result)->toContain('{"en":"Active","es":"Activo"}');
});

it('generates proper meta types from complex metadata', function () {
    $enumData = [
        'fqcn' => 'App\Enums\Status',
        'backingType' => 'string',
        'entries' => [
            [
                'key' => 'Active',
                'value' => 'active',
                'label' => 'Active',
                'meta' => [
                    'color' => 'green',
                    'priority' => 10,
                    'enabled' => true,
                    'tags' => ['important', 'visible'],
                ],
            ],
            [
                'key' => 'Inactive',
                'value' => 'inactive',
                'label' => 'Inactive',
                'meta' => [
                    'color' => 'red',
                    'priority' => 1,
                    'enabled' => false,
                    'tags' => ['hidden'],
                ],
            ],
        ],
        'options' => [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'inactive', 'label' => 'Inactive'],
        ],
    ];

    $result = $this->generator->generate(enumName: 'Status', enumData: $enumData, exportTypes: true);

    expect($result)->toContain('export type StatusMeta = {');
    expect($result)->toContain('readonly color: string;');
    expect($result)->toContain('readonly priority: number;');
    expect($result)->toContain('readonly enabled: boolean;');
    expect($result)->toContain('readonly tags: string[];');
});

it('includes all core utility methods', function () {
    $enumData = [
        'fqcn' => 'App\Enums\Status',
        'backingType' => 'string',
        'entries' => [
            [
                'key' => 'Active',
                'value' => 'active',
                'label' => 'Active',
                'meta' => [],
            ],
        ],
        'options' => [
            ['value' => 'active', 'label' => 'Active'],
        ],
    ];

    $result = $this->generator->generate('Status', $enumData);

    // Core properties and methods
    expect($result)->toContain('keys: KEYS,');
    expect($result)->toContain('values: VALUES,');
    expect($result)->toContain('labels(): readonly string[]');

    // Lookup methods with wider input types for flexibility
    expect($result)->toContain('from(value: string | null | undefined): typeof ENTRIES[number] | null');
    expect($result)->toContain('fromKey(key: string | null | undefined): typeof ENTRIES[number] | null');

    // Validation methods with type guards
    expect($result)->toContain('isValid(value: unknown): value is StatusValue');
    expect($result)->toContain('hasKey(key: unknown): key is StatusKey');

    // Should NOT contain removed methods (but may contain find() for lookup)
    expect($result)->not->toContain('random():');
    expect($result)->not->toContain('filter(predicate:');
    expect($result)->not->toContain('map<T>(mapper:');
    expect($result)->not->toContain('find(predicate:');
    expect($result)->not->toContain('some(predicate:');
    expect($result)->not->toContain('every(predicate:');
});

it('generates proper JSDoc documentation', function () {
    $enumData = [
        'fqcn' => 'App\Enums\Status',
        'backingType' => 'string',
        'entries' => [
            [
                'key' => 'Active',
                'value' => 'active',
                'label' => 'Active Status',
                'meta' => [],
            ],
        ],
        'options' => [
            ['value' => 'active', 'label' => 'Active Status'],
        ],
    ];

    $result = $this->generator->generate('Status', $enumData);

    expect($result)->toContain('/** Status - Generated from App\\Enums\\Status */');
});
