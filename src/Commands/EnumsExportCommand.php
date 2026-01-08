<?php

namespace Olivermbs\Enumshare\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Olivermbs\Enumshare\Exceptions\InvalidEnumException;
use Olivermbs\Enumshare\Support\EnumRegistry;
use Olivermbs\Enumshare\Support\EnumValidator;
use Olivermbs\Enumshare\Support\TypeScriptEnumGenerator;

class EnumsExportCommand extends Command
{
    protected $signature = 'enums:export
                            {--path= : Override the enum export path}
                            {--locale= : Override the locale for label generation}
                            {--format=typescript : Export format (typescript|json|both)}
                            {--index : Generate barrel index file}
                            {--types : Export TypeScript helper types}
                            {--minimal : Generate minimal output without utility methods}
                            {--force : Force overwrite existing files}
                            {--dry-run : Show what would be generated without writing files}
                            {--validate : Validate generated TypeScript syntax}
                            {--stats : Show detailed generation statistics}';

    protected $description = 'Export enums to TypeScript files';

    public function handle(EnumRegistry $registry, TypeScriptEnumGenerator $generator, EnumValidator $validator): int
    {
        try {
            $startTime = microtime(true);
            $locale = $this->option('locale') ?? config('enumshare.locale');
            $enumsDir = $this->option('path') ?? config('enumshare.path', resource_path('js/Enums'));
            $isDryRun = $this->option('dry-run');

            if ($isDryRun) {
                $this->comment('Dry run mode - no files will be written');
            }

            $this->info('Validating enums...');
            $this->validateConfiguration($validator);

            $this->info('Generating enum manifest...');
            $manifest = $registry->manifest($locale);

            if (empty($manifest)) {
                $this->warn('No enums found to export.');

                return self::SUCCESS;
            }

            $enumCount = count($manifest);
            $this->info("Found {$enumCount} enum(s) to process");

            if (! $isDryRun) {
                $this->ensureDirectoryExists($enumsDir);
            }

            $stats = $this->writeIndividualEnumFiles($manifest, $enumsDir, $generator);

            if ($this->option('index') && ! $isDryRun) {
                $this->generateIndexFile($enumsDir, $manifest);
            }

            $this->displayCompletionSummary($stats, $enumsDir, $startTime);

            return self::SUCCESS;
        } catch (InvalidEnumException $e) {
            $this->error("Validation failed: {$e->getMessage()}");
            $this->displayErrorContext('Validation Error', $e);

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error("Export failed: {$e->getMessage()}");
            $this->displayErrorContext('Export Error', $e);

            return self::FAILURE;
        }
    }

    protected function validateConfiguration(EnumValidator $validator): void
    {
        $configuredEnums = config('enumshare.enums', []);

        if (empty($configuredEnums) && ! config('enumshare.auto_discovery', false)) {
            $this->warn('No enums configured and auto-discovery is disabled.');

            return;
        }

        if (! empty($configuredEnums)) {
            $validation = $validator->validateMultipleEnumsForExport($configuredEnums);

            if (! empty($validation['errors'])) {
                $this->warn('Some configured enums have validation errors:');
                foreach ($validation['errors'] as $enumClass => $error) {
                    $this->warn("- {$enumClass}: {$error}");
                }
            }

            $validCount = count($validation['valid']);
            $totalCount = count($configuredEnums);

            if ($validCount > 0) {
                $this->info("{$validCount}/{$totalCount} configured enums are valid");
            }
        }
    }

    protected function ensureDirectoryExists(string $directory): void
    {
        try {
            if (! File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
                $this->info("Created directory: {$directory}");
            }
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to create directory: {$directory}", 0, $e);
        }
    }

    protected function writeIndividualEnumFiles(array $manifest, string $enumsDir, TypeScriptEnumGenerator $generator): array
    {
        $stats = [
            'generated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'total_size' => 0,
            'details' => [],
        ];

        $enumCount = count($manifest);
        $this->output->progressStart($enumCount);

        foreach ($manifest as $enumName => $enumData) {
            try {
                $this->line("Processing {$enumName}...");

                $content = $this->generateIndividualEnumFile(
                    $enumName,
                    $enumData,
                    $generator,
                    $this->option('types'),
                    $this->option('minimal')
                );
                $filePath = "{$enumsDir}/{$enumName}.ts";

                $contentSize = strlen($content);
                $stats['total_size'] += $contentSize;

                if ($this->option('validate')) {
                    $this->validateGeneratedContent($content, $enumName);
                }

                if ($this->option('dry-run')) {
                    $stats['details'][$enumName] = [
                        'status' => 'simulated',
                        'size' => $contentSize,
                        'path' => $filePath,
                    ];
                    $stats['skipped']++;
                } else {
                    $writeResult = $this->writeFileIfChanged($filePath, $content);

                    if ($writeResult === 'written') {
                        $stats['details'][$enumName] = [
                            'status' => 'generated',
                            'size' => $contentSize,
                            'path' => $filePath,
                        ];
                        $stats['generated']++;
                    } else {
                        $stats['details'][$enumName] = [
                            'status' => 'skipped',
                            'size' => $contentSize,
                            'path' => $filePath,
                            'reason' => $writeResult,
                        ];
                        $stats['skipped']++;
                    }
                }

                $this->output->progressAdvance();
            } catch (\Exception $e) {
                $stats['errors']++;
                $stats['details'][$enumName] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'path' => $filePath ?? null,
                ];

                $this->handleGenerationError($enumName, $enumData, $e);
                $this->output->progressAdvance();
            }
        }

        $this->output->progressFinish();

        return $stats;
    }

    protected function generateIndividualEnumFile(string $enumName, array $enumData, TypeScriptEnumGenerator $generator, bool $exportTypes = false, bool $minimal = false): string
    {
        try {
            $enumData = $this->ensureMetaIsObject($enumData);

            return $generator->generate($enumName, $enumData, $exportTypes, $minimal);
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to generate TypeScript for enum '{$enumName}': {$e->getMessage()}", 0, $e);
        }
    }

    protected function ensureMetaIsObject(array $enumData): array
    {
        if (isset($enumData['entries'])) {
            foreach ($enumData['entries'] as &$entry) {
                if (isset($entry['meta']) && is_array($entry['meta']) && empty($entry['meta'])) {
                    $entry['meta'] = (object) [];
                }
            }
        }

        return $enumData;
    }

    protected function writeFileIfChanged(string $filePath, string $content): string
    {
        if (! File::exists($filePath)) {
            File::put($filePath, $content);

            return 'written';
        }

        $existingContent = File::get($filePath);

        if (hash('xxh3', $existingContent) === hash('xxh3', $content)) {
            return 'unchanged';
        }

        if (! $this->option('force') && $this->input->isInteractive()) {
            if (! $this->confirm("File {$filePath} already exists. Overwrite?", false)) {
                return 'user_declined';
            }
        }

        File::put($filePath, $content);

        return 'written';
    }

    protected function validateGeneratedContent(string $content, string $enumName): void
    {
        if (! str_contains($content, "export const {$enumName}")) {
            throw new \RuntimeException("Generated content missing main export for {$enumName}");
        }

        if (substr_count($content, '{') !== substr_count($content, '}')) {
            throw new \RuntimeException("Unbalanced braces in generated TypeScript for {$enumName}");
        }

        if (empty(trim($content))) {
            throw new \RuntimeException("Generated content is empty for {$enumName}");
        }
    }

    protected function handleGenerationError(string $enumName, array $enumData, \Exception $e): void
    {
        $this->error("Failed to generate {$enumName}.ts: {$e->getMessage()}");

        if ($this->output->isVerbose()) {
            $this->line('  Class: '.($enumData['fqcn'] ?? 'Unknown'));
            $this->line('  Entries: '.count($enumData['entries'] ?? []));
            $this->line('  Backing Type: '.($enumData['backingType'] ?? 'none'));

            if (! empty($enumData['options'])) {
                $this->line('  Options: '.count($enumData['options']));
            }

            $this->error('  Stack trace:');
            $this->error('    '.str_replace("\n", "\n    ", $e->getTraceAsString()));
        } else {
            $this->comment('  Use -v for detailed error information');
        }
    }

    protected function displayErrorContext(string $errorType, \Exception $e): void
    {
        if ($this->output->isVerbose()) {
            $this->newLine();
            $this->error("{$errorType} Details:");
            $this->line('  File: '.$e->getFile());
            $this->line('  Line: '.$e->getLine());

            if ($e->getPrevious()) {
                $this->line('  Previous: '.$e->getPrevious()->getMessage());
            }

            $this->error('  Stack trace:');
            $this->error('    '.str_replace("\n", "\n    ", $e->getTraceAsString()));
        } else {
            $this->comment('Use -v for detailed error information');
        }
    }

    protected function generateIndexFile(string $enumsDir, array $manifest): void
    {
        $this->info('Generating index file...');

        $exports = [];
        $exports[] = '// Auto-generated barrel file for enum exports';
        $exports[] = '// This file is auto-generated. Do not edit manually.';
        $exports[] = '';

        foreach (array_keys($manifest) as $enumName) {
            $exports[] = "export { {$enumName} } from './{$enumName}';";
        }

        $exports[] = '';
        $content = implode("\n", $exports);
        $indexPath = "{$enumsDir}/index.ts";

        File::put($indexPath, $content);
        $this->comment('Generated index.ts');
    }

    protected function displayCompletionSummary(array $stats, string $enumsDir, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $totalFiles = $stats['generated'] + $stats['skipped'];

        $this->newLine();
        $this->info("Export completed in {$duration}ms");

        if ($this->option('dry-run')) {
            $this->comment('Dry run summary:');
        }

        $this->table([
            'Metric', 'Count',
        ], [
            ['Generated', $stats['generated']],
            ['Skipped', $stats['skipped']],
            ['Errors', $stats['errors']],
            ['Total Size', $this->formatBytes($stats['total_size'])],
            ['Output Path', $enumsDir],
        ]);

        if ($this->option('stats') && ! empty($stats['details'])) {
            $this->newLine();
            $this->info('Detailed Statistics:');

            $detailRows = [];
            foreach ($stats['details'] as $enumName => $detail) {
                $status = match ($detail['status']) {
                    'generated' => 'OK',
                    'skipped' => 'SKIP',
                    'simulated' => 'DRY',
                    'error' => 'ERR',
                    default => '?'
                };

                $size = isset($detail['size']) ? $this->formatBytes($detail['size']) : 'N/A';
                $note = $detail['error'] ?? ($detail['status'] === 'skipped' ? 'File exists' : '');

                $detailRows[] = [$enumName, $status, $size, $note];
            }

            $this->table([
                'Enum', 'Status', 'Size', 'Note',
            ], $detailRows);
        }

        if ($stats['generated'] > 0) {
            $this->info("Exported {$stats['generated']} enum(s) to: {$enumsDir}");
        }
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
