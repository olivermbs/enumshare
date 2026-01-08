<?php

namespace Olivermbs\Enumshare\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Olivermbs\Enumshare\Support\EnumRegistry;

class EnumsExportAllLocalesCommand extends Command
{
    protected $signature = 'enums:export-all-locales 
                            {--locales=* : Specific locales to export (default: all from config)}
                            {--base-path= : Override the base export path}';

    protected $description = 'Export enums for multiple locales';

    public function handle(EnumRegistry $registry): int
    {
        $locales = $this->option('locales') ?: $this->getConfiguredLocales();
        $basePath = $this->option('base-path') ?: config('enumshare.path', resource_path('js/Enums'));

        if (empty($locales)) {
            $this->warn('No locales configured. Add locales to config or use --locales option.');

            return self::SUCCESS;
        }

        $this->info('Exporting enums for multiple locales...');

        foreach ($locales as $locale) {
            $this->info("Generating for locale: {$locale}");

            $localeDir = "{$basePath}/{$locale}";

            $manifest = $registry->manifest($locale);

            if (empty($manifest)) {
                $this->warn("No enums found for locale: {$locale}");

                continue;
            }

            $this->ensureDirectoryExists($localeDir);

            $this->writeIndividualEnumFiles($manifest, $localeDir, $locale);

            $enumCount = count($manifest);
            $this->line("  ✅ {$enumCount} enum(s) exported to: {$localeDir}");
        }

        $this->info('✅ Multi-locale export completed!');

        return self::SUCCESS;
    }

    protected function getConfiguredLocales(): array
    {
        // Try to get from app config, fallback to common locales
        return config('app.supported_locales', [
            config('app.locale', 'en'),
            config('app.fallback_locale', 'en'),
        ]);
    }

    protected function ensureDirectoryExists(string $directory): void
    {
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }

    protected function writeIndividualEnumFiles(array $manifest, string $enumsDir, string $locale): void
    {
        // Copy EnumRuntime.ts from package resources
        $this->copyEnumRuntime($enumsDir);

        foreach ($manifest as $enumName => $enumData) {
            $content = $this->generateIndividualEnumFile($enumName, $enumData, $locale);
            $filePath = "{$enumsDir}/{$enumName}.ts";
            File::put($filePath, $content);
        }
    }

    protected function copyEnumRuntime(string $enumsDir): void
    {
        $sourcePath = __DIR__.'/../Resources/EnumRuntime.ts';
        $targetPath = "{$enumsDir}/EnumRuntime.ts";

        if (File::exists($sourcePath)) {
            File::copy($sourcePath, $targetPath);
            $this->info('📋 Copied EnumRuntime.ts');
        }
    }

    protected function generateIndividualEnumFile(string $enumName, array $enumData, string $locale): string
    {
        $content = "// This file is auto-generated. Do not edit manually.\n";
        $content .= "// Locale: {$locale}\n";
        $content .= "import { createEnumProxy, type EnumProxy } from '../EnumRuntime';\n\n";

        // Generate the enum data as a constant
        $enumDataJson = json_encode($enumData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $content .= "const {$enumName}Data = {$enumDataJson};\n\n";

        // Generate TypeScript types
        $keys = array_map(fn ($entry) => "'{$entry['key']}'", $enumData['entries']);
        $keyUnion = implode(' | ', $keys);

        if ($enumData['backingType'] === 'string') {
            $values = array_map(fn ($entry) => "'{$entry['value']}'", $enumData['entries']);
        } elseif ($enumData['backingType'] === 'int') {
            $values = array_map(fn ($entry) => $entry['value'], $enumData['entries']);
        } else {
            $values = $keys;
        }
        $valueUnion = implode(' | ', $values);

        $content .= "export type {$enumName}Key = {$keyUnion};\n";
        $content .= "export type {$enumName}Value = {$valueUnion};\n\n";

        $content .= "export interface {$enumName}Entry {\n";
        $content .= "  key: {$enumName}Key;\n";
        $content .= '  value: '.($enumData['backingType'] ? "{$enumName}Value" : 'null').";\n";
        $content .= "  label: string;\n";
        $content .= "  meta: Record<string, any>;\n";
        $content .= "}\n\n";

        $content .= "export interface {$enumName}Option {\n";
        $content .= "  value: {$enumName}Value;\n";
        $content .= "  label: string;\n";
        $content .= "}\n\n";

        // Generate the enum proxy interface
        $entryRecord = "Record<{$enumName}Key, {$enumName}Entry>";
        $content .= "export interface {$enumName}Enum extends {$entryRecord} {\n";
        $content .= "  name: string;\n";
        $content .= "  entries: {$enumName}Entry[];\n";
        $content .= "  options: {$enumName}Option[];\n";
        $content .= "  keys(): {$enumName}Key[];\n";
        $content .= "  values(): {$enumName}Value[];\n";
        $content .= "  labels(): string[];\n";
        $content .= "  from(value: {$enumName}Value): {$enumName}Entry | null;\n";
        $content .= "}\n\n";

        // Export the enum instance
        $content .= "export const {$enumName}: {$enumName}Enum = createEnumProxy({$enumName}Data) as {$enumName}Enum;\n";
        $content .= "export default {$enumName};\n";

        return $content;
    }
}
