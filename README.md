# Enumshare

[![Latest Version on Packagist](https://img.shields.io/packagist/v/olivermbs/enumshare.svg?style=flat-square)](https://packagist.org/packages/olivermbs/enumshare)
[![Tests](https://img.shields.io/github/actions/workflow/status/olivermbs/enumshare/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/olivermbs/enumshare/actions?query=workflow%3Arun-tests+branch%3Amain)

Export PHP Enums to TypeScript. Simple, type-safe, zero runtime dependencies.

## Installation

```bash
composer require olivermbs/enumshare
```

## Quick Start

### 1. Add the trait to your enum

```php
<?php

namespace App\Enums;

use Olivermbs\Enumshare\Attributes\Label;
use Olivermbs\Enumshare\Attributes\Meta;
use Olivermbs\Enumshare\Concerns\SharesWithFrontend;

enum Status: string
{
    use SharesWithFrontend;

    #[Label('Active')]
    #[Meta(['color' => 'green'])]
    case Active = 'active';

    #[Label('Inactive')]
    #[Meta(['color' => 'red'])]
    case Inactive = 'inactive';
}
```

### 2. Export

```bash
php artisan enums:export
```

### 3. Use in TypeScript

```typescript
import { Status } from '@/enums/Status';

Status.Active.value    // 'active'
Status.Active.label    // 'Active'
Status.Active.meta     // { color: 'green' }

Status.from('active')  // Status.Active entry
Status.isValid('active') // true
Status.options         // [{ value: 'active', label: 'Active' }, ...]
```

## Output Modes

### Default (Full)

Includes lookup maps, type guards, and utility methods:

```bash
php artisan enums:export
```

### Minimal

Wayfinder-style simple output (~15 lines per enum):

```bash
php artisan enums:export --minimal
```

```typescript
export type Status = 'active' | 'inactive';

export const Active: Status = 'active';
export const Inactive: Status = 'inactive';

export const Status = { Active, Inactive } as const;
```

## Configuration

```bash
php artisan vendor:publish --tag="enumshare-config"
```

```php
// config/enumshare.php
return [
    'enums' => [
        App\Enums\Status::class,
    ],
    'path' => resource_path('js/Enums'),
    'auto_discovery' => true,
    'auto_paths' => ['app/Enums'],
];
```

## Commands

```bash
php artisan enums:export              # Export enums
php artisan enums:export --minimal    # Simple output
php artisan enums:export --types      # Export type definitions
php artisan enums:export --force      # Overwrite existing
php artisan enums:discover            # List discovered enums
php artisan enums:watch               # Watch for changes
```

## Attributes

| Attribute | Description |
|-----------|-------------|
| `#[Label('Text')]` | Static label |
| `#[TranslatedLabel('key')]` | Translation key |
| `#[Meta(['key' => 'value'])]` | Metadata |
| `#[ExportMethod]` | Export method result |

## Auto-Regeneration with Vite

For automatic regeneration during development, install [Laravel Wayfinder](https://github.com/laravel/wayfinder):

```bash
composer require laravel/wayfinder
npm install @laravel/vite-plugin-wayfinder
```

Then configure Vite to watch your enum files:

```javascript
// vite.config.js
import { wayfinder } from '@laravel/vite-plugin-wayfinder';

export default defineConfig({
  plugins: [
    wayfinder({
      command: 'php artisan enums:export --force',
      patterns: ['app/Enums/**/*.php', 'lang/**/*.php', 'config/enumshare.php'],
    }),
  ],
});
```

> **Note:** Wayfinder is optional - only needed for auto-regeneration. You can always run `php artisan enums:export` manually or use `php artisan enums:watch`.

## Testing

```bash
composer test
```

## License

MIT
