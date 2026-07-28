<?php

use Olivermbs\Enumshare\Support\TypeScriptEnumGenerator;
use Symfony\Component\Process\Process;

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
    expect($result)->toContain('readonly tags: readonly string[];');
});

it('marks metadata omitted from some entries as optional', function () {
    $enumData = [
        'fqcn' => 'App\Enums\Status',
        'backingType' => 'string',
        'entries' => [
            ['key' => 'Active', 'value' => 'active', 'label' => 'Active', 'meta' => ['color' => 'green']],
            ['key' => 'Inactive', 'value' => 'inactive', 'label' => 'Inactive', 'meta' => []],
        ],
    ];

    $result = $this->generator->generate('Status', $enumData, true);

    expect($result)->toContain('readonly color?: string;');
});

it('emits custom method data and derives optional entry properties', function () {
    $enumData = [
        'fqcn' => 'App\Enums\ContactType',
        'backingType' => 'int',
        'entries' => [
            [
                'key' => 'Email',
                'value' => 1,
                'label' => 'Email',
                'meta' => [],
                'isInstant' => true,
                'requiresPhoneNumber' => false,
            ],
            [
                'key' => 'Postal',
                'value' => 2,
                'label' => 'Postal',
                'meta' => [],
                'isInstant' => false,
            ],
        ],
    ];

    $result = $this->generator->generate('ContactType', $enumData, true);

    expect($result)
        ->toContain('isInstant: true')
        ->toContain('requiresPhoneNumber: false')
        ->toContain('readonly isInstant: boolean;')
        ->toContain('readonly requiresPhoneNumber?: boolean;');
});

it('types empty heterogeneous and nested lists and compiles them under strict checks', function () {
    $typescript = dirname(__DIR__, 2).'/node_modules/.bin/tsc';

    if (! is_file($typescript)) {
        $this->markTestSkipped('TypeScript is not installed.');
    }

    $enumData = [
        'fqcn' => 'App\Enums\ListData',
        'backingType' => 'string',
        'entries' => [
            [
                'key' => 'Example',
                'value' => 'example',
                'label' => 'Example',
                'meta' => [],
                'emptyItems' => [],
                'mixedItems' => [1, 'two'],
                'nestedItems' => [[1, 'two'], [true]],
            ],
        ],
    ];
    $result = $this->generator->generate('ListData', $enumData, true);

    expect($result)
        ->toContain('readonly emptyItems: readonly unknown[];')
        ->toContain('readonly mixedItems: readonly (number | string)[];')
        ->toContain('readonly nestedItems: readonly (readonly (number | string)[] | readonly boolean[])[];');

    $directory = sys_get_temp_dir().'/enumshare-list-ts-'.uniqid('', true);
    mkdir($directory);
    $file = $directory.'/ListData.ts';

    try {
        file_put_contents($file, $result);

        $process = new Process([
            $typescript,
            '--strict',
            '--noUnusedLocals',
            '--noEmit',
            '--ignoreConfig',
            '--target',
            'ES2020',
            '--skipLibCheck',
            $file,
        ]);
        $process->run();

        expect($process->isSuccessful())
            ->toBeTrue($process->getErrorOutput().$process->getOutput());
    } finally {
        if (is_file($file)) {
            unlink($file);
        }
        rmdir($directory);
    }
});

it('qualifies runtime globals shadowed by Map and Object enum cases', function () {
    $typescript = dirname(__DIR__, 2).'/node_modules/.bin/tsc';

    if (! is_file($typescript)) {
        $this->markTestSkipped('TypeScript is not installed.');
    }

    $viewModeData = [
        'fqcn' => 'App\Enums\ViewMode',
        'backingType' => 'string',
        'entries' => [
            ['key' => 'Map', 'value' => 'map', 'label' => 'Map', 'meta' => []],
            ['key' => 'List', 'value' => 'list', 'label' => 'List', 'meta' => []],
        ],
    ];
    $contentTypeData = [
        'fqcn' => 'App\Enums\ContentType',
        'backingType' => 'string',
        'entries' => [
            [
                'key' => 'Object',
                'value' => 'object',
                'label' => ['en' => 'Object', 'de' => 'Objekt'],
                'meta' => [],
            ],
            [
                'key' => 'Text',
                'value' => 'text',
                'label' => ['en' => 'Text', 'de' => 'Text'],
                'meta' => [],
            ],
        ],
    ];
    $generatedFiles = [
        'ViewMode.ts' => $this->generator->generate('ViewMode', $viewModeData, true),
        'ContentType.ts' => $this->generator->generate('ContentType', $contentTypeData, true),
    ];

    expect($generatedFiles['ViewMode.ts'])
        ->toContain('new globalThis.Map(')
        ->and($generatedFiles['ContentType.ts'])
        ->toContain('globalThis.Object.values(label)');

    $directory = sys_get_temp_dir().'/enumshare-global-ts-'.uniqid('', true);
    mkdir($directory);

    try {
        foreach ($generatedFiles as $filename => $content) {
            $file = $directory.'/'.$filename;
            file_put_contents($file, $content);

            $process = new Process([
                $typescript,
                '--strict',
                '--noUnusedLocals',
                '--noEmit',
                '--ignoreConfig',
                '--target',
                'ES2020',
                '--skipLibCheck',
                $file,
            ]);
            $process->run();

            expect($process->isSuccessful())
                ->toBeTrue($process->getErrorOutput().$process->getOutput());
        }
    } finally {
        foreach (glob($directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
});

it('uses JSON string literals in full and minimal modes', function () {
    $enumData = [
        'fqcn' => 'App\Enums\Message',
        'backingType' => 'string',
        'entries' => [
            [
                'key' => 'Greeting',
                'value' => "hello\n'world/世界",
                'label' => "Hello\n\"world\"/世界",
                'meta' => ['message' => "Meta\n'value/世界"],
            ],
        ],
    ];

    $full = $this->generator->generate('Message', $enumData);
    $minimal = $this->generator->generate('Message', $enumData, false, 'minimal');

    expect($full)
        ->toContain(json_encode("hello\n'world/世界", JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
        ->toContain(json_encode("Hello\n\"world\"/世界", JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
        ->toContain(json_encode(['message' => "Meta\n'value/世界"], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
        ->and($minimal)
        ->toContain(json_encode("hello\n'world/世界", JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
});

it('uses the entry type and compiles under strict unused checks with and without exported types', function () {
    $typescript = dirname(__DIR__, 2).'/node_modules/.bin/tsc';

    if (! is_file($typescript)) {
        $this->markTestSkipped('TypeScript is not installed.');
    }

    $enumData = [
        'fqcn' => 'App\Enums\Status',
        'backingType' => 'string',
        'entries' => [
            [
                'key' => 'Active',
                'value' => 'active',
                'label' => 'Active',
                'meta' => ['tags' => ['visible']],
                'isVisible' => true,
                'aliases' => ['enabled'],
            ],
            [
                'key' => 'Inactive',
                'value' => 'inactive',
                'label' => 'Inactive',
                'meta' => [],
                'isVisible' => false,
            ],
        ],
    ];
    $directory = sys_get_temp_dir().'/enumshare-ts-'.uniqid('', true);
    mkdir($directory);

    try {
        foreach ([false, true] as $exportTypes) {
            $file = $directory.'/Status-'.($exportTypes ? 'exported' : 'local').'.ts';
            file_put_contents($file, $this->generator->generate('Status', $enumData, $exportTypes));

            $process = new Process([
                $typescript,
                '--strict',
                '--noUnusedLocals',
                '--noEmit',
                '--ignoreConfig',
                '--target',
                'ES2020',
                '--skipLibCheck',
                $file,
            ]);
            $process->run();

            expect($process->isSuccessful())
                ->toBeTrue($process->getErrorOutput().$process->getOutput());
        }
    } finally {
        foreach (glob($directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }

    $result = $this->generator->generate('Status', $enumData);

    expect($result)
        ->toContain('from(value: string | null | undefined): StatusEntry | null')
        ->toContain('fromKey(key: string | null | undefined): StatusEntry | null');
});

it('compiles non-backed enums under strict unused checks with and without exported types', function () {
    $typescript = dirname(__DIR__, 2).'/node_modules/.bin/tsc';

    if (! is_file($typescript)) {
        $this->markTestSkipped('TypeScript is not installed.');
    }

    $enumData = [
        'fqcn' => 'App\Enums\Direction',
        'backingType' => null,
        'entries' => [
            ['key' => 'North', 'value' => null, 'label' => 'North', 'meta' => []],
            ['key' => 'South', 'value' => null, 'label' => 'South', 'meta' => []],
        ],
    ];
    $directory = sys_get_temp_dir().'/enumshare-pure-ts-'.uniqid('', true);
    mkdir($directory);

    try {
        foreach ([false, true] as $exportTypes) {
            $file = $directory.'/Direction-'.($exportTypes ? 'exported' : 'local').'.ts';
            file_put_contents($file, $this->generator->generate('Direction', $enumData, $exportTypes));

            $process = new Process([
                $typescript,
                '--strict',
                '--noUnusedLocals',
                '--noEmit',
                '--ignoreConfig',
                '--target',
                'ES2020',
                '--skipLibCheck',
                $file,
            ]);
            $process->run();

            expect($process->isSuccessful())
                ->toBeTrue($process->getErrorOutput().$process->getOutput());
        }
    } finally {
        foreach (glob($directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }

    $result = $this->generator->generate('Direction', $enumData);

    expect($result)->toContain('const VALUES: readonly DirectionValue[] = KEYS;');
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
    expect($result)->toContain('Active: "active",');
    expect($result)->toContain('Inactive: "inactive",');
    expect($result)->toContain('export type Status = typeof Status[keyof typeof Status];');
    expect($result)->not->toContain('ENTRIES');
    expect($result)->not->toContain('from(');
});
