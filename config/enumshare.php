<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enums to Export
    |--------------------------------------------------------------------------
    |
    | List of enum class FQCNs that should be exported to the frontend.
    | Any PHP enum can be exported - no trait required.
    | All enums are extracted via reflection.
    |
    */
    'enums' => [
        // App\Enums\TripStatus::class,
        // App\Enums\UserRole::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Path
    |--------------------------------------------------------------------------
    |
    | Path where the enum TypeScript files will be written.
    |
    */
    'path' => resource_path('js/Enums'),

    /*
    |--------------------------------------------------------------------------
    | Output Mode
    |--------------------------------------------------------------------------
    |
    | 'full' - Includes labels, meta, helpers (from, isValid, options, etc.)
    | 'minimal' - Just values and types (~10 lines per enum)
    |
    */
    'mode' => 'full',

    /*
    |--------------------------------------------------------------------------
    | Barrel Index File
    |--------------------------------------------------------------------------
    |
    | Generate an index.ts barrel file re-exporting all enums on every export.
    | The --index flag forces this for a single run.
    |
    */
    'index' => false,

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | Default locale and additional locales for translations.
    | Set locale to null to use app()->getLocale().
    | Add locales to export multilingual enum labels.
    |
    */
    'locale' => null,
    'locales' => [
        // 'en',
        // 'fr',
        // 'es',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovery
    |--------------------------------------------------------------------------
    |
    | Automatically discover all PHP enums in specified paths.
    |
    | Note: This will export ALL enums found in the configured paths.
    | For tighter control, disable auto-discovery and list enums explicitly
    | in the 'enums' array above.
    | Note: Enums are keyed by short name; duplicate basenames across
    | namespaces will cause a name collision error.
    |
    */
    'auto_discovery' => env('ENUMSHARE_AUTODISCOVERY', true),
    'auto_paths' => [
        'app/Enums',
        // 'app/Models/Enums',
        // 'src/Domain/*/Enums',
    ],

    /*
    |--------------------------------------------------------------------------
    | Language Namespace
    |--------------------------------------------------------------------------
    |
    | Namespace for automatic label resolution when no Label attribute is present.
    | Labels resolve from: lang("{namespace}.{EnumShortName}.{CaseName}")
    |
    */
    'lang_namespace' => 'enums',
];
