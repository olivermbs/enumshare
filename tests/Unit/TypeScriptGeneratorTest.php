<?php

use Olivermbs\Enumshare\Support\TypeScriptEnumGenerator;

beforeEach(function () {
    $this->generator = new TypeScriptEnumGenerator;
});

it('generates TypeScript for simple string enum', function () {
    $enumData = [
        'fqcn' => 'App\Enums\Status',
        'backingType' => 'string',
        'entries' => [
            ['key' => 'Active', 'value' => 'active', 'label' => 'Active Status', 'meta' => ['color' => 'green']],
            ['key' => 'Inactive', 'value' => 'inactive', 'label' => 'Inactive Status', 'meta' => ['color' => 'red']],
        ],
    ];

    $result = $this->generator->generate(enumName: 'Status', enumData: $enumData, exportTypes: true);

    expect($result)->toContain('export type StatusMeta');
    expect($result)->toContain('export type StatusKey');
    expect($result)->toContain('export type StatusValue');
    expect($result)->toContain('export const Status = {');
    expect($result)->toContain('Active,');
    expect($result)->toContain('Inactive,');
});

it('generates TypeScript for integer enum', function () {
    $enumData = [
        'fqcn' => 'App\Enums\Priority',
        'backingType' => 'int',
        'entries' => [
            ['key' => 'Low', 'value' => 1, 'label' => 'Low Priority', 'meta' => []],
            ['key' => 'High', 'value' => 10, 'label' => 'High Priority', 'meta' => []],
        ],
    ];

    $result = $this->generator->generate(enumName: 'Priority', enumData: $enumData, exportTypes: true);

    expect($result)->toContain('readonly value: PriorityValue;');
    expect($result)->toContain('value: 1,');
    expect($result)->toContain('value: 10,');
});

it('generates TypeScript for pure enum without backing type', function () {
    $enumData = [
        'fqcn' => 'App\Enums\Direction',
        'backingType' => null,
        'entries' => [
            ['key' => 'North', 'value' => null, 'label' => 'North Direction', 'meta' => []],
            ['key' => 'South', 'value' => null, 'label' => 'South Direction', 'meta' => []],
        ],
    ];

    $result = $this->generator->generate(enumName: 'Direction', enumData: $enumData, exportTypes: true);

    expect($result)->toContain('readonly value: null;');
    expect($result)->toContain('value: null,');
});

it('generates TypeScript for multilingual enum', function () {
    $enumData = [
        'fqcn' => 'App\Enums\Status',
        'backingType' => 'string',
        'entries' => [
            ['key' => 'Active', 'value' => 'active', 'label' => ['en' => 'Active', 'es' => 'Activo'], 'meta' => []],
        ],
    ];

    $result = $this->generator->generate('Status', $enumData);

    expect($result)->toContain('labels(locale?: string)');
    expect($result)->toContain('{"en":"Active","es":"Activo"}');
    expect($result)->toContain('resolveLabel');
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
                'meta' => ['color' => 'green', 'priority' => 10, 'enabled' => true, 'tags' => ['important', 'visible']],
            ],
            [
                'key' => 'Inactive',
                'value' => 'inactive',
                'label' => 'Inactive',
                'meta' => ['color' => 'red', 'priority' => 1, 'enabled' => false, 'tags' => ['hidden']],
            ],
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
            ['key' => 'Active', 'value' => 'active', 'label' => 'Active', 'meta' => []],
        ],
    ];

    $result = $this->generator->generate('Status', $enumData);

    expect($result)->toContain('keys: KEYS,');
    expect($result)->toContain('values: VALUES,');
    expect($result)->toContain('options: OPTIONS,');
    expect($result)->toContain('from(value:');
    expect($result)->toContain('fromKey(key:');
    expect($result)->toContain('isValid(value:');
    expect($result)->toContain('hasKey(key:');
    expect($result)->toContain('labels()');
});

it('generates minimal mode output', function () {
    $enumData = [
        'fqcn' => 'App\Enums\Status',
        'backingType' => 'string',
        'entries' => [
            ['key' => 'Active', 'value' => 'active', 'label' => 'Active', 'meta' => []],
            ['key' => 'Inactive', 'value' => 'inactive', 'label' => 'Inactive', 'meta' => []],
        ],
    ];

    $result = $this->generator->generate('Status', $enumData, false, 'minimal');

    expect($result)->toContain('export const Status = {');
    expect($result)->toContain("Active: 'active',");
    expect($result)->toContain("Inactive: 'inactive',");
    expect($result)->toContain('export type Status = typeof Status[keyof typeof Status];');
    expect($result)->not->toContain('ENTRIES');
    expect($result)->not->toContain('from(');
});
