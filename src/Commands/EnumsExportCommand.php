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
                            {--force : Force overwrite existing files}
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
                'list' => $this->option('list'),
            ]);

            if (! empty($result['warnings'])) {
                $this->warn('Some configured enums are invalid:');
                foreach ($result['warnings'] as $enumClass => $error) {
                    $this->warn("- {$enumClass}: {$error}");
                }
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

            if (($result['generated'] ?? 0) === 0 && ($result['skipped'] ?? 0) === 0) {
                $this->warn('No enums found to export.');

                return self::SUCCESS;
            }

            $path = $result['path'] ?? config('enumshare.path', resource_path('js/Enums'));
            $generated = $result['generated'] ?? 0;
            $skipped = $result['skipped'] ?? 0;

            $this->info("Exported {$generated} enum(s) to: {$path}");
            if ($skipped > 0) {
                $this->comment("Skipped {$skipped} enum(s) (existing files).");
            }

            return self::SUCCESS;
        } catch (InvalidEnumException $e) {
            $this->error("Validation failed: {$e->getMessage()}");

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error("Export failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
