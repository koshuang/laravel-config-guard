<?php

namespace Koshuang\LaravelConfigGuard\Tests;

use Koshuang\LaravelConfigGuard\Support\EnvFileInspector;
use PHPUnit\Framework\TestCase;

class EnvFileInspectorTest extends TestCase
{
    public function test_it_detects_duplicate_env_keys(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($path, "APP_ENV=local\nSTRIPE_SECRET=one\nSTRIPE_SECRET=two\n");

        $duplicates = (new EnvFileInspector())->duplicates($path);

        $this->assertSame(['STRIPE_SECRET' => [2, 3]], $duplicates);

        @unlink($path);
    }
}
