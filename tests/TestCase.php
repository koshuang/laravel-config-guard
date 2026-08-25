<?php

namespace Koshuang\LaravelConfigGuard\Tests;

use Koshuang\LaravelConfigGuard\ConfigGuardServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ConfigGuardServiceProvider::class];
    }
}
