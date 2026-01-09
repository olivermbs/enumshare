<?php

namespace Olivermbs\Enumshare\Support;

use Illuminate\Support\Facades\File;
use Olivermbs\Enumshare\Exceptions\InvalidEnumException;

class EnumExporter
{
    public function __construct(
        protected EnumRegistry $registry,
        protected TypeScriptEnumGenerator $generator,
        protected EnumValidator $validator
    ) {}

    public function export(array $options): array
    {
        $locale = $options['locale'] ?? config('enumshare.locale');
        $path = $options['path'] ?? config('enumshare.path', resource_path('js/Enums'));
        $exportTypes = $options['types'] ?? false;
        $force = $options['force'] ?? false;
        $index = $options['index'] ?? false;
        $listOnly = $options['list'] ?? false;

        $warnings = $this->validateConfiguration();

        $manifest = $this->registry->manifest($locale);

        if ($listOnly) {
            return [
                'mode' => 'list',
                'enums' => array_keys($manifest),
                'path' => $path,
                'warnings' => $warnings,
            ];
        }

        if (empty($manifest)) {
            return [
                'mode' => 'export',
                'generated' => 0,
                'skipped' => 0,
                'path' => $path,
                'warnings' => $warnings,
            ];
        }

        $this->ensureDirectoryExists($path);

        $stats = $this->writeIndividualEnumFiles($manifest, $path, $exportTypes, $force);

        if ($index) {
            $this->generateIndexFile($path, $manifest);
        }

        $stats['path'] = $path;
        $stats['warnings'] = $warnings;

        return $stats;
    }

    protected function validateConfiguration(): array
    {
        $configuredEnums = config('enumshare.enums', []);
        $autoDiscovery = config('enumshare.auto_discovery', false);

        $configWarnings = [];
        if (empty($configuredEnums) && ! $autoDiscovery) {
            $configWarnings[] = 'No enums configured and auto-discovery is disabled.';
        }

        $enumErrors = [];
        if (! empty($configuredEnums)) {
            $validation = $this->validator->validateMultipleEnumsForExport($configuredEnums);
            $enumErrors = $validation['errors'] ?? [];
        }

        return [
            'config' => $configWarnings,
            'enums' => $enumErrors,
        ];
    }

    protected function ensureDirectoryExists(string $directory): void
    {
        if (! File::isDirectory($directory)) {
            try {
                File::makeDirectory($directory, 0755, true);
            } catch (\Exception $e) {
                throw new \RuntimeException("Failed to create directory: {$directory}", 0, $e);
            }
        }
    }

    protected function writeIndividualEnumFiles(array $manifest, string $enumsDir, bool $exportTypes, bool $force): array
    {
        $stats = [
            'mode' => 'export',
            'generated' => 0,
            'skipped' => 0,
        ];

        $mode = config('enumshare.mode', 'full');

        foreach ($manifest as $enumName => $enumData) {
            $content = $this->generator->generate($enumName, $enumData, $exportTypes, $mode);
            $filePath = "{$enumsDir}/{$enumName}.ts";

            if (! $this->writeFileIfChanged($filePath, $content, $force)) {
                $stats['skipped']++;
                continue;
            }

            $stats['generated']++;
        }

        return $stats;
    }

    protected function writeFileIfChanged(string $filePath, string $content, bool $force): bool
    {
        if (File::exists($filePath) && ! $force) {
            $existingContent = File::get($filePath);

            if (hash('xxh3', $existingContent) === hash('xxh3', $content)) {
                return false;
            }
        }

        File::put($filePath, $content);

        return true;
    }

    protected function generateIndexFile(string $enumsDir, array $manifest): void
    {
        $lines = [
            '// Auto-generated barrel file for enum exports',
            '// This file is auto-generated. Do not edit manually.',
            '',
        ];

        foreach (array_keys($manifest) as $enumName) {
            $lines[] = "export { {$enumName} } from './{$enumName}';";
        }

        $lines[] = '';

        File::put("{$enumsDir}/index.ts", implode("\n", $lines));
    }
}
