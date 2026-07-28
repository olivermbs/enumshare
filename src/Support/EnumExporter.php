<?php

namespace Olivermbs\Enumshare\Support;

use Illuminate\Support\Facades\File;
use Olivermbs\Enumshare\Attributes\DontExport;
use Olivermbs\Enumshare\Exceptions\ExportException;
use ReflectionClass;

class EnumExporter
{
    public function __construct(
        protected EnumRegistry $registry,
        protected TypeScriptEnumGenerator $generator
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

        $stats = $this->writeEnumFiles($manifest, $path, $exportTypes, $force);

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

        foreach ($configuredEnums as $enumClass) {
            if (class_exists($enumClass) && (new ReflectionClass($enumClass))->getAttributes(DontExport::class) !== []) {
                $configWarnings[] = "Enum {$enumClass} is listed in config but marked #[DontExport]; skipping.";
            }
        }

        $enumErrors = ! empty($configuredEnums)
            ? $this->registry->validateEnums($configuredEnums)
            : [];

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
            } catch (\Throwable $e) {
                throw ExportException::directoryCreationFailed($directory, $e);
            }
        }
    }

    protected function writeEnumFiles(array $manifest, string $enumsDir, bool $exportTypes, bool $force): array
    {
        $stats = ['mode' => 'export', 'generated' => 0, 'skipped' => 0];
        $mode = config('enumshare.mode', 'full');

        foreach ($manifest as $enumName => $enumData) {
            $content = $this->generator->generate($enumName, $enumData, $exportTypes, $mode);
            $filePath = "{$enumsDir}/{$enumName}.ts";

            if ($this->shouldSkipFile($filePath, $content, $force)) {
                $stats['skipped']++;
            } else {
                File::put($filePath, $content);
                $stats['generated']++;
            }
        }

        return $stats;
    }

    protected function shouldSkipFile(string $filePath, string $content, bool $force): bool
    {
        if ($force || ! File::exists($filePath)) {
            return false;
        }

        return hash('xxh3', File::get($filePath)) === hash('xxh3', $content);
    }

    protected function generateIndexFile(string $enumsDir, array $manifest): void
    {
        $exports = array_map(
            fn ($name) => "export { {$name} } from './{$name}';",
            array_keys($manifest)
        );

        $content = "// Auto-generated. Do not edit.\n\n".implode("\n", $exports)."\n";

        File::put("{$enumsDir}/index.ts", $content);
    }
}
