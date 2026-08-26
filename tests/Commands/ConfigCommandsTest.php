<?php

namespace Koshuang\LaravelConfigGuard\Tests\Commands;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Artisan;
use Koshuang\LaravelConfigGuard\Support\ConfigValidator;
use Koshuang\LaravelConfigGuard\Tests\TestCase;

class ConfigCommandsTest extends TestCase
{
    public function test_lint_command_succeeds_for_valid_contract(): void
    {
        $base = $this->makeProject([
            'config/config-guard.php' => "<?php return ['application_config' => ['config/payment.php']];",
            'config/payment.php' => '<?php return [\'secret\' => env(\'STRIPE_SECRET\')];',
            '.env.example' => "STRIPE_SECRET=\n",
            '.env.testing' => '',
        ]);

        $exitCode = Artisan::call('config:lint', ['--path' => $base]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Configuration contract is valid.', $output);

        $this->removeProject($base);
    }

    public function test_lint_command_fails_when_env_is_used_outside_config(): void
    {
        $base = $this->makeProject([
            'app/Checkout.php' => '<?php env(\'STRIPE_SECRET\');',
            '.env.example' => '',
            '.env.testing' => '',
        ]);

        $exitCode = Artisan::call('config:lint', ['--path' => $base]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('env() used outside config/: app/Checkout.php', $output);

        $this->removeProject($base);
    }

    public function test_lint_command_loads_application_config_from_target_project(): void
    {
        $base = $this->makeProject([
            'config/config-guard.php' => "<?php return ['application_config' => ['config/payment.php']];",
            'config/payment.php' => '<?php return [\'secret\' => env(\'STRIPE_SECRET\')];',
            '.env.example' => '',
            '.env.testing' => '',
        ]);

        config()->set('config-guard.application_config', []);

        $exitCode = Artisan::call('config:lint', ['--path' => $base]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            'STRIPE_SECRET is referenced by application config but missing from .env.example',
            $output,
        );

        $this->removeProject($base);
    }

    public function test_lint_command_uses_target_env_file_configuration(): void
    {
        $base = $this->makeProject([
            'config/config-guard.php' => "<?php return ['env_files' => ['.env.ci']];",
            '.env.example' => "STRIPE_SECRET=one\nSTRIPE_SECRET=two\n",
            '.env.testing' => '',
            '.env.ci' => "API_KEY=one\nAPI_KEY=two\n",
        ]);

        $exitCode = Artisan::call('config:lint', ['--path' => $base]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('.env.ci contains duplicate key API_KEY on lines 1, 2', $output);
        $this->assertStringNotContainsString('.env.example contains duplicate key STRIPE_SECRET', $output);

        $this->removeProject($base);
    }

    public function test_lint_command_fails_on_duplicate_env_keys(): void
    {
        $base = $this->makeProject([
            '.env.example' => "STRIPE_SECRET=one\nSTRIPE_SECRET=two\n",
            '.env.testing' => '',
        ]);

        $exitCode = Artisan::call('config:lint', ['--path' => $base]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            '.env.example contains duplicate key STRIPE_SECRET on lines 1, 2',
            $output,
        );

        $this->removeProject($base);
    }

    public function test_validate_command_fails_when_required_config_is_missing(): void
    {
        /** @var Application $application */
        $application = $this->app;
        $application->detectEnvironment(fn (): string => 'production');

        config()->set('config-guard.required.production', ['payment.stripe.secret']);
        config()->set('payment.stripe.secret', null);

        $exitCode = Artisan::call('config:validate');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('✗ payment.stripe.secret', $output);
        $this->assertStringContainsString('Required configuration is missing.', $output);
    }

    public function test_validate_command_succeeds_when_required_config_is_present(): void
    {
        /** @var Application $application */
        $application = $this->app;
        $application->detectEnvironment(fn (): string => 'production');

        config()->set('config-guard.required.production', ['payment.stripe.secret']);
        config()->set('payment.stripe.secret', 'secret');

        $exitCode = Artisan::call('config:validate');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('✓ payment.stripe.secret', $output);
        $this->assertStringContainsString('Required configuration is valid.', $output);
    }

    public function test_validator_uses_cached_config_instead_of_changed_raw_environment(): void
    {
        $configFile = config_path('config-guard-cache-test.php');
        $environmentKey = 'CONFIG_GUARD_CACHE_SECRET';
        $originalEnvironment = getenv($environmentKey);

        file_put_contents(
            $configFile,
            "<?php return ['secret' => env('{$environmentKey}')];",
        );
        $this->setEnvironmentValue($environmentKey, 'cached-secret');

        try {
            Artisan::call('config:clear');
            $this->assertSame(0, Artisan::call('config:cache'));
            $this->assertFileExists($this->app->getCachedConfigPath());

            $this->setEnvironmentValue($environmentKey, 'changed-after-cache');
            $this->reloadConfiguration();

            $this->assertTrue($this->app->configurationIsCached());
            $this->assertSame('cached-secret', config('config-guard-cache-test.secret'));

            /** @var ConfigValidator $validator */
            $validator = $this->app->make(ConfigValidator::class);
            $this->assertSame([], $validator->missing(['config-guard-cache-test.secret']));
        } finally {
            Artisan::call('config:clear');
            @unlink($configFile);
            $this->restoreEnvironmentValue($environmentKey, $originalEnvironment);
        }
    }

    public function test_validator_reports_missing_value_from_cached_config_even_if_env_is_added_later(): void
    {
        $configFile = config_path('config-guard-cache-missing-test.php');
        $environmentKey = 'CONFIG_GUARD_CACHE_MISSING_SECRET';
        $originalEnvironment = getenv($environmentKey);

        file_put_contents(
            $configFile,
            "<?php return ['secret' => env('{$environmentKey}')];",
        );
        $this->restoreEnvironmentValue($environmentKey, false);

        try {
            Artisan::call('config:clear');
            $this->assertSame(0, Artisan::call('config:cache'));

            $this->setEnvironmentValue($environmentKey, 'added-after-cache');
            $this->reloadConfiguration();

            $this->assertTrue($this->app->configurationIsCached());
            $this->assertNull(config('config-guard-cache-missing-test.secret'));

            /** @var ConfigValidator $validator */
            $validator = $this->app->make(ConfigValidator::class);
            $this->assertSame(
                ['config-guard-cache-missing-test.secret'],
                $validator->missing(['config-guard-cache-missing-test.secret']),
            );
        } finally {
            Artisan::call('config:clear');
            @unlink($configFile);
            $this->restoreEnvironmentValue($environmentKey, $originalEnvironment);
        }
    }

    private function reloadConfiguration(): void
    {
        $this->app->forgetInstance('config');
        (new LoadConfiguration)->bootstrap($this->app);
    }

    private function setEnvironmentValue(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function restoreEnvironmentValue(string $key, string|false $value): void
    {
        if ($value === false) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        $this->setEnvironmentValue($key, $value);
    }

    /** @param array<string, string> $files */
    private function makeProject(array $files): string
    {
        $base = sys_get_temp_dir().'/config-guard-command-'.bin2hex(random_bytes(4));
        mkdir($base, 0777, true);

        foreach ($files as $path => $content) {
            $fullPath = $base.'/'.$path;
            $directory = dirname($fullPath);

            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($fullPath, $content);
        }

        return $base;
    }

    private function removeProject(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = array_diff(scandir($path) ?: [], ['.', '..']);

        foreach ($items as $item) {
            $fullPath = $path.'/'.$item;

            if (is_dir($fullPath)) {
                $this->removeProject($fullPath);
            } else {
                @unlink($fullPath);
            }
        }

        @rmdir($path);
    }
}
