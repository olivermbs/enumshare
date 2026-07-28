<?php

namespace Olivermbs\Enumshare\Commands;

use Illuminate\Console\Command;
use Olivermbs\Enumshare\Exceptions\InvalidEnumException;
use Olivermbs\Enumshare\Support\EnumExporter;

class EnumsExportCommand extends Command
{
    protected $signature = 'enums:export
                            {--path= : Override the enum export path}
                            {--locale= : Override the locale for label generation}
                            {--index : Generate barrel index file}
                            {--types : Export TypeScript helper types}
                            {--force : Rewrite all files, even if unchanged}
                            {--prune : Delete generated files for enums that no longer exist}
                            {--check : Verify generated files are up to date without writing}
                            {--list : List enums that would be exported}';

    protected $description = 'Export enums to TypeScript files';

    public function handle(EnumExporter $exporter): int
    {
        try {
            $result = $exporter->export([
                'path' => $this->option('path'),
                'locale' => $this->option('locale'),
                'index' => $this->option('index'),
                'types' => $this->option('types'),
                'force' => $this->option('force'),
                'prune' => $this->option('prune'),
                'check' => $this->option('check'),
                'list' => $this->option('list'),
            ]);

            $warnings = $result['warnings'] ?? [];

            foreach ($warnings['config'] ?? [] as $warning) {
                $this->warn($warning);
            }

            foreach ($warnings['enums'] ?? [] as $enumClass => $error) {
                $this->warn("Invalid enum {$enumClass}: {$error}");
            }

            if (($result['mode'] ?? null) === 'list') {
                $enums = $result['enums'] ?? [];

                if (empty($enums)) {
                    $this->warn('No enums found to export.');

                    return self::SUCCESS;
                }

                $this->info('Enums to export:');
                foreach ($enums as $enum) {
                    $this->line("  - {$enum}");
                }

                return self::SUCCESS;
            }

            if (($result['mode'] ?? null) === 'check') {
                foreach ($result['stale'] as $file) {
                    $this->error("Stale: {$file}");
                }
                foreach ($result['missing'] as $file) {
                    $this->error("Missing: {$file}");
                }
                foreach ($result['orphans'] as $file) {
                    $this->warn("Orphaned (would be pruned): {$file}");
                }

                if ($result['stale'] === [] && $result['missing'] === []) {
                    $this->info('Everything up to date.');

                    return self::SUCCESS;
                }

                $this->error('Generated enums are out of date. Run: php artisan enums:export');

                return self::FAILURE;
            }

            if (($result['generated'] ?? 0) === 0 && ($result['skipped'] ?? 0) === 0) {
                $this->warn('No enums found to export.');

                foreach ($result['pruned'] ?? [] as $prunedFile) {
                    $this->comment("Pruned {$prunedFile}");
                }

                return self::SUCCESS;
            }

            $path = $result['path'] ?? config('enumshare.path', resource_path('js/Enums'));
            $generated = $result['generated'] ?? 0;
            $skipped = $result['skipped'] ?? 0;

            $this->info("Exported {$generated} enum(s) to: {$path}");
            if ($skipped > 0) {
                $this->comment("Skipped {$skipped} enum(s) (content unchanged).");
            }

            foreach ($result['pruned'] ?? [] as $prunedFile) {
                $this->comment("Pruned {$prunedFile}");
            }

            return self::SUCCESS;
        } catch (InvalidEnumException $e) {
            $this->error("Validation failed: {$e->getMessage()}");

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error("Export failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
