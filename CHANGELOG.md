# Changelog

All notable changes to `enumshare` will be documented in this file.

## v1.2.0 - 2026-07-29

### Added

- Laravel 13 support (Pest 4, Testbench 11, Symfony 8 components)

## v1.1.1 - 2026-07-28

### Fixes

- Prune keeps generated files on Windows (separator-agnostic path comparison)
- Prune runs when all enums have been retired (empty manifest)
- Normalize export paths with trailing slashes so prune never deletes fresh output
- Reject enum case names matching the enum's own short name

## v1.1.0 - 2026-07-28

### Features

- Add `--check` for CI drift detection without writing files
- Add `--prune` for marker-guarded orphan cleanup
- Add `#[DontExport]` to exclude enums from export
- Add the `index` config option for automatic barrel file generation
- Add Enumshare details to `php artisan about`

### Fixes

- Export `#[ExportMethod]` results to generated TypeScript entries
- Use JSON encoding for safe TypeScript string escaping
- Generate TypeScript compatible with `noUnusedLocals`
- Validate enum and case names against reserved and generated identifiers
- Qualify runtime globals through `globalThis` to prevent shadowing
- Support glob and absolute paths during enum auto-discovery
- Mark metadata keys omitted from some enum cases as optional

## v1.0.0 - 2026-01-08

### First Stable Release

#### Features

- Export PHP enums to TypeScript with type safety
- `--minimal` flag for simple Wayfinder-style output
- Auto-discovery of enums in configured paths
- Labels, metadata, and translated labels support
- Content hashing (skips unchanged files for stability)
- `/* eslint-disable */` header for linter compatibility
- Deterministic output ordering

#### Breaking Changes from alpha

- Package renamed from `olivermbs/laravel-enumshare` to `olivermbs/enumshare`
- Namespace changed from `Olivermbs\LaravelEnumshare` to `Olivermbs\Enumshare`
- Service provider renamed to `EnumshareServiceProvider`

#### Migration from alpha

Update your composer.json:

```json
"olivermbs/enumshare": "^1.0"
```

Update namespace imports:

```php
use Olivermbs\Enumshare\Concerns\SharesWithFrontend;
use Olivermbs\Enumshare\Attributes\Label;
```
