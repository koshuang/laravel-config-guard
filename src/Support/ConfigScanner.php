<?php

namespace Koshuang\LaravelConfigGuard\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ConfigScanner
{
    /** @return array<string, list<string>> */
    public function envReferences(string $configPath): array
    {
        $references = [];

        foreach ($this->phpFiles($configPath) as $file) {
            $content = file_get_contents($file);

            if ($content === false) {
                continue;
            }

            preg_match_all('/\benv\(\s*(?:key\s*:\s*)?[\'\"]([A-Za-z_][A-Za-z0-9_]*)[\'\"]/', $content, $matches);

            foreach ($matches[1] as $key) {
                $references[$key][] = $file;
            }
        }

        return $references;
    }

    /** @return list<string> */
    public function envUsageOutsideConfig(string $basePath): array
    {
        $violations = [];
        $configPath = realpath($basePath.'/config') ?: $basePath.'/config';

        foreach ($this->phpFiles($basePath, ['vendor', 'storage', 'bootstrap/cache']) as $file) {
            $real = realpath($file) ?: $file;

            if (str_starts_with($real, rtrim($configPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                continue;
            }

            $content = file_get_contents($file);

            if ($content !== false && preg_match('/\benv\s*\(/', $content)) {
                $violations[] = $file;
            }
        }

        return $violations;
    }

    /**
     * @param  list<string>  $excluded
     * @return list<string>
     */
    private function phpFiles(string $path, array $excluded = []): array
    {
        if (is_file($path)) {
            return pathinfo($path, PATHINFO_EXTENSION) === 'php' ? [$path] : [];
        }

        if (! is_dir($path)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $pathname = $file->getPathname();
            $relative = ltrim(str_replace(rtrim($path, DIRECTORY_SEPARATOR), '', $pathname), DIRECTORY_SEPARATOR);
            $isExcluded = false;

            foreach ($excluded as $dir) {
                if ($relative === $dir || str_starts_with($relative, $dir.DIRECTORY_SEPARATOR)) {
                    $isExcluded = true;
                    break;
                }
            }

            if ($isExcluded) {
                continue;
            }

            $files[] = $pathname;
        }

        return $files;
    }
}
