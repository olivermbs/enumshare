<?php

namespace Olivermbs\Enumshare\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Olivermbs\Enumshare\Support\EnumRegistry;

class EnumsWatchCommand extends Command
{
    protected $signature = 'enums:watch 
                            {--interval=2 : Check interval in seconds}';

    protected $description = 'Watch enum files and regenerate automatically';

    protected array $fileHashes = [];

    public function handle(EnumRegistry $registry): int
    {
        $interval = (int) $this->option('interval');

        $this->info('👀 Watching enum files for changes...');
        $this->info('💡 Run alongside: npm run dev');
        $this->line('Press Ctrl+C to stop');

        // Initial export
        $this->export($registry);

        while (true) {
            if ($this->hasChanges()) {
                $this->line('📝 Changes detected, regenerating...');
                $this->export($registry);
                $this->info('✅ Enums updated');
            }
            sleep($interval);
        }
    }

    protected function hasChanges(): bool
    {
        $files = $this->getWatchedFiles();

        foreach ($files as $file) {
            if (! file_exists($file)) {
                continue;
            }

            $hash = md5_file($file);

            if (! isset($this->fileHashes[$file])) {
                $this->fileHashes[$file] = $hash;

                continue;
            }

            if ($this->fileHashes[$file] !== $hash) {
                $this->fileHashes[$file] = $hash;

                return true;
            }
        }

        return false;
    }

    protected function getWatchedFiles(): array
    {
        $files = [];

        // Watch enum files from autodiscovery paths
        if (config('enumshare.auto_discovery')) {
            foreach (config('enumshare.auto_paths', []) as $path) {
                $fullPath = base_path($path);
                if (File::isDirectory($fullPath)) {
                    $files = array_merge($files, $this->getPhpFiles($fullPath));
                }
            }
        }

        // Watch configured enum files
        foreach (config('enumshare.enums', []) as $enumClass) {
            if (class_exists($enumClass)) {
                $reflection = new \ReflectionClass($enumClass);
                $files[] = $reflection->getFileName();
            }
        }

        // Watch translation files
        $langDir = base_path('lang');
        if (File::isDirectory($langDir)) {
            $files = array_merge($files, $this->getEnumTranslationFiles($langDir));
        }

        // Watch config
        $files[] = config_path('enumshare.php');

        return array_filter($files, 'file_exists');
    }

    protected function getPhpFiles(string $dir): array
    {
        if (! File::isDirectory($dir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getRealPath();
            }
        }

        return $files;
    }

    protected function getEnumTranslationFiles(string $langDir): array
    {
        $files = [];
        $dirs = File::directories($langDir);

        foreach ($dirs as $localeDir) {
            $enumFile = $localeDir.'/enums.php';
            if (file_exists($enumFile)) {
                $files[] = $enumFile;
            }
        }

        return $files;
    }

    protected function export(EnumRegistry $registry): void
    {
        $this->call('enums:export');
    }
}
