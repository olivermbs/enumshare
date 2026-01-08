# Changelog

All notable changes to `enumshare` will be documented in this file.

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
