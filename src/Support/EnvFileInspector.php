<?php

namespace Koshuang\LaravelConfigGuard\Support;

class EnvFileInspector
{
    /** @return array<string, list<int>> */
    public function keys(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $keys = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $index => $line) {
            if (preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=/', $line, $match)) {
                $keys[$match[1]][] = $index + 1;
            }
        }

        return $keys;
    }

    /** @return array<string, list<int>> */
    public function duplicates(string $path): array
    {
        return array_filter($this->keys($path), fn (array $lines) => count($lines) > 1);
    }
}
