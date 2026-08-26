<?php

namespace Koshuang\LaravelConfigGuard\Commands;

use Illuminate\Console\Command;
use Koshuang\LaravelConfigGuard\Support\ConfigScanner;
use Koshuang\LaravelConfigGuard\Support\EnvFileInspector;

class LintConfigCommand extends Command
{
    protected $signature = 'config:lint {--path=}';

    protected $description = 'Lint the Laravel configuration contract';

    public function handle(ConfigScanner $scanner, EnvFileInspector $env): int
    {
        $pathOption = $this->option('path');
        $explicitPath = is_string($pathOption) && $pathOption !== '';
        $basePath = $explicitPath ? $pathOption : base_path();
        $guardConfig = $this->guardConfig($basePath, $explicitPath);
        $lintConfig = isset($guardConfig['lint']) && is_array($guardConfig['lint']) ? $guardConfig['lint'] : [];
        $failed = false;

        if ((bool) ($lintConfig['env_outside_config'] ?? true)) {
            foreach ($scanner->envUsageOutsideConfig($basePath) as $file) {
                $this->error('env() used outside config/: '.$this->relative($basePath, $file));
                $failed = true;
            }
        }

        if ((bool) ($lintConfig['missing_example_keys'] ?? true)) {
            $references = [];
            $configuredPaths = $guardConfig['application_config'] ?? [];
            $applicationConfig = is_array($configuredPaths)
                ? array_values(array_filter($configuredPaths, 'is_string'))
                : [];

            foreach ($applicationConfig as $path) {
                $fullPath = $basePath.'/'.ltrim($path, '/\\');

                foreach ($scanner->envReferences($fullPath) as $key => $files) {
                    $references[$key] = array_merge($references[$key] ?? [], $files);
                }
            }

            $exampleKeys = array_keys($env->keys($basePath.'/.env.example'));

            foreach (array_diff(array_keys($references), $exampleKeys) as $key) {
                $this->error("{$key} is referenced by application config but missing from .env.example");
                $failed = true;
            }
        }

        if ((bool) ($lintConfig['duplicate_env_keys'] ?? true)) {
            $configuredFiles = $guardConfig['env_files'] ?? ['.env.example', '.env.testing'];
            $envFiles = is_array($configuredFiles)
                ? array_values(array_filter($configuredFiles, 'is_string'))
                : [];

            foreach ($envFiles as $filename) {
                foreach ($env->duplicates($basePath.'/'.$filename) as $key => $lines) {
                    $this->error(sprintf('%s contains duplicate key %s on lines %s', $filename, $key, implode(', ', $lines)));
                    $failed = true;
                }
            }
        }

        if ($failed) {
            return self::FAILURE;
        }

        $this->info('Configuration contract is valid.');

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function guardConfig(string $basePath, bool $explicitPath): array
    {
        if (! $explicitPath) {
            $configured = config('config-guard', []);

            return is_array($configured) ? $configured : [];
        }

        $defaults = require __DIR__.'/../../config/config-guard.php';
        $targetFile = rtrim($basePath, '/\\').'/config/config-guard.php';

        if (! is_file($targetFile)) {
            return $defaults;
        }

        $target = require $targetFile;

        return is_array($target) ? array_replace_recursive($defaults, $target) : $defaults;
    }

    private function relative(string $basePath, string $file): string
    {
        return ltrim(str_replace(rtrim($basePath, DIRECTORY_SEPARATOR), '', $file), DIRECTORY_SEPARATOR);
    }
}
