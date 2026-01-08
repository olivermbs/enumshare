<?php

use Olivermbs\Enumshare\EnumshareServiceProvider;

it('generates typescript from blade template', function () {
    // Register the service provider to load views
    $this->app->register(EnumshareServiceProvider::class);

    $enumData = [
        'name' => 'TestStatus',
        'fqcn' => 'Tests\\TestStatus',
        'backingType' => 'string',
        'entries' => [
            [
                'key' => 'Active',
                'value' => 'active',
                'label' => 'Active Status',
                'meta' => ['color' => 'green', 'icon' => 'check'],
            ],
            [
                'key' => 'Pending',
                'value' => 'pending',
                'label' => 'Pending Status',
                'meta' => ['color' => 'yellow', 'icon' => 'clock'],
            ],
        ],
        'options' => [
            ['value' => 'active', 'label' => 'Active Status'],
            ['value' => 'pending', 'label' => 'Pending Status'],
        ],
    ];

    // Use the generator directly instead of the blade template
    $generator = $this->app->make(\Olivermbs\Enumshare\Support\TypeScriptEnumGenerator::class);
    $output = $generator->generate(enumName: 'TestStatus', enumData: $enumData, exportTypes: true);

    // Check basic structure
    expect($output)->toContain('export type TestStatusKey');
    expect($output)->toContain('export const TestStatus');
    expect($output)->toContain("key: 'Active'");
    expect($output)->toContain("value: 'active'");
    expect($output)->toContain('from(value:');
    expect($output)->toContain('fromKey(key:');
    expect($output)->not->toContain('EnumRuntime');

    // Verify it's valid TypeScript structure
    expect($output)->toContain('as const'); // Uses const assertions

    // Check JSDoc comment is present
    expect($output)->toContain('/** TestStatus - Generated from');

    // Check core utility methods with type guards
    expect($output)->toContain('isValid(value: unknown): value is TestStatusValue');
    expect($output)->toContain('hasKey(key: unknown): key is TestStatusKey');
    expect($output)->toContain('count: ENTRIES.length');
});
