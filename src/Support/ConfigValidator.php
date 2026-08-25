<?php

namespace Koshuang\LaravelConfigGuard\Support;

class ConfigValidator
{
    /**
     * @param  list<string>  $required
     * @return list<string>
     */
    public function missing(array $required): array
    {
        return array_values(array_filter($required, function (string $key): bool {
            $value = config($key);

            return $value === null || $value === '';
        }));
    }
}
