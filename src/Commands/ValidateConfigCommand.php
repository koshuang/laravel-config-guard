<?php

namespace Koshuang\LaravelConfigGuard\Commands;

use Illuminate\Console\Command;
use Koshuang\LaravelConfigGuard\Support\ConfigValidator;

class ValidateConfigCommand extends Command
{
    protected $signature = 'config:validate';

    protected $description = 'Validate required resolved Laravel configuration';

    public function handle(ConfigValidator $validator): int
    {
        $environment = app()->environment();
        $configured = config("config-guard.required.{$environment}", []);
        $required = is_array($configured)
            ? array_values(array_filter($configured, 'is_string'))
            : [];
        $missing = $validator->missing($required);

        foreach ($required as $key) {
            $this->line((in_array($key, $missing, true) ? '✗ ' : '✓ ').$key);
        }

        if ($missing !== []) {
            $this->error('Required configuration is missing.');

            return self::FAILURE;
        }

        $this->info('Required configuration is valid.');

        return self::SUCCESS;
    }
}
