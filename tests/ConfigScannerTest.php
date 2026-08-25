<?php

namespace Koshuang\LaravelConfigGuard\Tests;

use Koshuang\LaravelConfigGuard\Support\ConfigScanner;
use PHPUnit\Framework\TestCase;

class ConfigScannerTest extends TestCase
{
    public function test_it_finds_env_references_in_config_directory(): void
    {
        $base = sys_get_temp_dir().'/config-guard-'.bin2hex(random_bytes(4));
        mkdir($base.'/config', 0777, true);
        file_put_contents($base.'/config/payment.php', "<?php return ['secret' => env('STRIPE_SECRET')];");

        $references = new ConfigScanner()->envReferences($base.'/config');

        $this->assertArrayHasKey('STRIPE_SECRET', $references);

        @unlink($base.'/config/payment.php');
        @rmdir($base.'/config');
        @rmdir($base);
    }

    public function test_it_finds_env_references_in_a_single_owned_config_file(): void
    {
        $base = sys_get_temp_dir().'/config-guard-'.bin2hex(random_bytes(4));
        mkdir($base.'/config', 0777, true);
        $path = $base.'/config/payment.php';
        file_put_contents($path, "<?php return ['secret' => env('STRIPE_SECRET')];");

        $references = new ConfigScanner()->envReferences($path);

        $this->assertArrayHasKey('STRIPE_SECRET', $references);

        @unlink($path);
        @rmdir($base.'/config');
        @rmdir($base);
    }

    public function test_it_finds_env_usage_outside_config(): void
    {
        $base = sys_get_temp_dir().'/config-guard-'.bin2hex(random_bytes(4));
        mkdir($base.'/config', 0777, true);
        mkdir($base.'/app', 0777, true);
        file_put_contents($base.'/config/payment.php', "<?php return ['secret' => env('STRIPE_SECRET')];");
        file_put_contents($base.'/app/Checkout.php', "<?php env('STRIPE_SECRET');");

        $violations = new ConfigScanner()->envUsageOutsideConfig($base);

        $this->assertCount(1, $violations);
        $this->assertStringEndsWith('app/Checkout.php', str_replace('\\', '/', $violations[0]));

        @unlink($base.'/app/Checkout.php');
        @unlink($base.'/config/payment.php');
        @rmdir($base.'/app');
        @rmdir($base.'/config');
        @rmdir($base);
    }
}
