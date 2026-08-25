<?php

namespace Koshuang\LaravelConfigGuard\Tests\Commands;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Koshuang\LaravelConfigGuard\Tests\TestCase;

class ConfigCommandsTest extends TestCase
{
    public function test_lint_command_succeeds_for_valid_contract(): void
    {
        $base = $this->makeProject([
            'config/payment.php' => '<?php return [\'secret\' => env(\'STRIPE_SECRET\')];',
            '.env.example' => "STRIPE_SECRET=\n",
            '.env.testing' => '',
        ]);

        config()->set('config-guard.application_config', ['config/payment.php']);

        $exitCode = Artisan::call('config:lint', ['--path' => $base]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Configuration contract is valid.', Artisan::output());

        $this->removeProject($base);
    }

    public function test_lint_command_fails_when_env_is_used_outside_config(): void
    {
        $base = $this->makeProject([
            'app/Checkout.php' => '<?php env(\'STRIPE_SECRET\');',
            '.env.example' => '',
            '.env.testing' => '',
        ]);

        config()->set('config-guard.application_config', []);

        $exitCode = Artisan::call('config:lint', ['--path' => $base]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('env() used outside config/: app/Checkout.php', Artisan::output());

        $this->removeProject($base);
    }

    public function test_lint_command_fails_when_application_env_is_missing_from_example(): void
    {
        $base = $this->makeProject([
            'config/payment.php' => '<?php return [\'secret\' => env(\'STRIPE_SECRET\')];',
            '.env.example' => '',
            '.env.testing' => '',
        ]);

        config()->set('config-guard.application_config', ['config/payment.php']);

        $exitCode = Artisan::call('config:lint', ['--path' => $base]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            'STRIPE_SECRET is referenced by application config but missing from .env.example',
            Artisan::output(),
        );

        $this->removeProject($base);
    }

    public function test_lint_command_fails_on_duplicate_env_keys(): void
    {
        $base = $this->makeProject([
            '.env.example' => "STRIPE_SECRET=one\nSTRIPE_SECRET=two\n",
            '.env.testing' => '',
        ]);

        config()->set('config-guard.application_config', []);

        $exitCode = Artisan::call('config:lint', ['--path' => $base]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            '.env.example contains duplicate key STRIPE_SECRET on lines 1, 2',
            Artisan::output(),
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

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('✗ payment.stripe.secret', Artisan::output());
        $this->assertStringContainsString('Required configuration is missing.', Artisan::output());
    }

    public function test_validate_command_succeeds_when_required_config_is_present(): void
    {
        /** @var Application $application */
        $application = $this->app;
        $application->detectEnvironment(fn (): string => 'production');

        config()->set('config-guard.required.production', ['payment.stripe.secret']);
        config()->set('payment.stripe.secret', 'secret');

        $exitCode = Artisan::call('config:validate');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('✓ payment.stripe.secret', Artisan::output());
        $this->assertStringContainsString('Required configuration is valid.', Artisan::output());
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
