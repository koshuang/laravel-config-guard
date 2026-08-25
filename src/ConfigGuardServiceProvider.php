<?php

namespace Koshuang\LaravelConfigGuard;

use Illuminate\Support\ServiceProvider;
use Koshuang\LaravelConfigGuard\Commands\LintConfigCommand;
use Koshuang\LaravelConfigGuard\Commands\ValidateConfigCommand;

class ConfigGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config-guard.php', 'config-guard');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/config-guard.php' => config_path('config-guard.php'),
        ], 'config-guard-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                LintConfigCommand::class,
                ValidateConfigCommand::class,
            ]);
        }
    }
}
