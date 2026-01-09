<?php

namespace Olivermbs\Enumshare\Support;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class EnumAutoDiscovery
{
    public function __construct(
        protected array $paths = [],
        protected ?EnumValidator $validator = null
    ) {}

    public function discover(): array
    {
        return $this->performDiscovery();
    }

    protected function performDiscovery(): array
    {
        $enums = [];

        foreach ($this->paths as $path) {
            $enums = array_merge($enums, $this->scanPath($path));
        }

        return array_unique($enums);
    }

    protected function scanPath(string $path): array
    {
        $fullPath = base_path($path);

        if (! File::isDirectory($fullPath)) {
            return [];
        }

        $enums = [];
        $finder = Finder::create()
            ->files()
            ->name('*.php')
            ->in($fullPath);

        foreach ($finder as $file) {
            $enumClasses = $this->extractEnumClassesFromFile($file->getRealPath());

            foreach ($enumClasses as $enumClass) {
                if ($this->isValidFrontendEnum($enumClass)) {
                    $enums[] = $enumClass;
                }
            }
        }

        return $enums;
    }

    protected function extractEnumClassesFromFile(string $filePath): array
    {
        $content = File::get($filePath);
        $enums = [];
        $tokens = \PhpToken::tokenize($content);
        $namespace = '';

        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token->id === T_NAMESPACE) {
                $namespace = $this->parseNamespace($tokens, $i + 1);
                continue;
            }

            if ($token->id === T_ENUM) {
                $enumName = $this->parseEnumName($tokens, $i + 1);

                if ($enumName) {
                    $enums[] = $namespace ? $namespace.'\\'.$enumName : $enumName;
                }
            }
        }

        return array_values(array_unique($enums));
    }

    protected function parseNamespace(array $tokens, int $start): string
    {
        $parts = [];
        $count = count($tokens);

        for ($i = $start; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token->text === ';' || $token->text === '{') {
                break;
            }

            if ($token->id === T_STRING || $token->id === T_NS_SEPARATOR || $token->id === T_NAME_QUALIFIED || $token->id === T_NAME_FULLY_QUALIFIED) {
                $parts[] = $token->text;
            }
        }

        return implode('', $parts);
    }

    protected function parseEnumName(array $tokens, int $start): ?string
    {
        $count = count($tokens);

        for ($i = $start; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token->id === T_STRING) {
                return $token->text;
            }

            if (! $token->isIgnorable()) {
                break;
            }
        }

        return null;
    }

    protected function isValidFrontendEnum(string $enumClass): bool
    {
        if ($this->validator) {
            return $this->validator->isValidEnumForExport($enumClass);
        }

        return class_exists($enumClass);
    }
}
