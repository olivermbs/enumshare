<?php

namespace Olivermbs\Enumshare\Support;

use Illuminate\Support\Facades\File;
use Olivermbs\Enumshare\Attributes\DontExport;
use Olivermbs\Enumshare\Exceptions\ExportException;
use ReflectionClass;

class EnumExporter
{
    protected const array GENERATED_MARKERS = ['// Auto-generated from', '// Auto-generated. Do not edit.'];

    public function __construct(
        protected EnumRegistry $registry,
        protected TypeScriptEnumGenerator $generator
    ) {}

    public function export(array $options): array
    {
        $locale = $options['locale'] ?? config('enumshare.locale');
        $path = $options['path'] ?? config('enumshare.path', resource_path('js/Enums'));
        $path = rtrim($path, '/'.DIRECTORY_SEPARATOR);
        if ($path === '') {
            $path = DIRECTORY_SEPARATOR;
        } elseif (preg_match('/^[A-Za-z]:$/', $path) === 1) {
            $path .= DIRECTORY_SEPARATOR;
        }
        $exportTypes = $options['types'] ?? false;
        $force = $options['force'] ?? false;
        $index = ($options['index'] ?? false) || config('enumshare.index', false);
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

        if ($options['check'] ?? false) {
            return $this->check($manifest, $path, $exportTypes, $index, $warnings);
        }

        if (empty($manifest)) {
            $result = [
                'mode' => 'export',
                'generated' => 0,
                'skipped' => 0,
                'path' => $path,
                'warnings' => $warnings,
            ];

            if ($options['prune'] ?? false) {
                $result['pruned'] = $this->pruneOrphans($path, []);
            }

            return $result;
        }

        $this->ensureDirectoryExists($path);

        $stats = $this->writeEnumFiles($manifest, $path, $exportTypes, $force);

        if ($index) {
            $this->generateIndexFile($path, $manifest);
        }

        $keepPaths = $stats['files'];
        if ($index) {
            $keepPaths[] = "{$path}/index.ts";
        }

        if ($options['prune'] ?? false) {
            $stats['pruned'] = $this->pruneOrphans($path, $keepPaths);
        }
        unset($stats['files']);

        $stats['path'] = $path;
        $stats['warnings'] = $warnings;

        return $stats;
    }

    protected function check(array $manifest, string $enumsDir, bool $exportTypes, bool $index, array $warnings): array
    {
        $mode = config('enumshare.mode', 'full');
        $result = ['mode' => 'check', 'upToDate' => [], 'stale' => [], 'missing' => [], 'orphans' => []];
        $expectedPaths = [];

        foreach ($manifest as $enumName => $enumData) {
            $content = $this->generator->generate($enumName, $enumData, $exportTypes, $mode);
            $filePath = "{$enumsDir}/{$enumName}.ts";
            $expectedPaths[] = $filePath;
            $result[$this->classify($filePath, $content)][] = basename($filePath);
        }

        if ($index) {
            $indexPath = "{$enumsDir}/index.ts";
            $expectedPaths[] = $indexPath;
            $result[$this->classify($indexPath, $this->indexContent($manifest))][] = 'index.ts';
        }

        $result['orphans'] = array_map('basename', $this->findOrphans($enumsDir, $expectedPaths));
        $result['path'] = $enumsDir;
        $result['warnings'] = $warnings;

        return $result;
    }

    protected function classify(string $filePath, string $content): string
    {
        if (! File::exists($filePath)) {
            return 'missing';
        }

        if (hash('xxh3', File::get($filePath)) === hash('xxh3', $content)) {
            return 'upToDate';
        }

        return 'stale';
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
        $stats = ['mode' => 'export', 'generated' => 0, 'skipped' => 0, 'files' => []];
        $mode = config('enumshare.mode', 'full');

        foreach ($manifest as $enumName => $enumData) {
            $content = $this->generator->generate($enumName, $enumData, $exportTypes, $mode);
            $filePath = "{$enumsDir}/{$enumName}.ts";
            $stats['files'][] = $filePath;

            if ($this->shouldSkipFile($filePath, $content, $force)) {
                $stats['skipped']++;
            } else {
                File::put($filePath, $content);
                $stats['generated']++;
            }
        }

        return $stats;
    }

    public function findOrphans(string $path, array $keepPaths): array
    {
        if (! File::isDirectory($path)) {
            return [];
        }

        $orphans = [];
        $normalizedKeepPaths = array_map(
            fn (string $keepPath): string => $this->normalizePath($keepPath),
            $keepPaths
        );

        foreach (File::files($path) as $file) {
            if ($file->getExtension() !== 'ts') {
                continue;
            }

            $filePath = $file->getPathname();

            if (in_array($this->normalizePath($filePath), $normalizedKeepPaths, true)) {
                continue;
            }

            if (! $this->hasGeneratedMarker($filePath)) {
                continue;
            }

            $orphans[] = $filePath;
        }

        return $orphans;
    }

    protected function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    public function pruneOrphans(string $path, array $keepPaths): array
    {
        $deleted = [];

        foreach ($this->findOrphans($path, $keepPaths) as $orphan) {
            File::delete($orphan);
            $deleted[] = basename($orphan);
        }

        return $deleted;
    }

    protected function hasGeneratedMarker(string $filePath): bool
    {
        $content = File::get($filePath);

        foreach (self::GENERATED_MARKERS as $marker) {
            if (str_contains($content, $marker)) {
                return true;
            }
        }

        return false;
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
        File::put("{$enumsDir}/index.ts", $this->indexContent($manifest));
    }

    protected function indexContent(array $manifest): string
    {
        $exports = array_map(
            fn ($name) => "export { {$name} } from './{$name}';",
            array_keys($manifest)
        );

        return "// Auto-generated. Do not edit.\n\n".implode("\n", $exports)."\n";
    }
}
