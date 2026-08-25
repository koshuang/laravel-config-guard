<?php

namespace Koshuang\LaravelConfigGuard\Tests;

use Koshuang\LaravelConfigGuard\Support\ConfigValidator;

class ConfigValidatorTest extends TestCase
{
    public function test_it_reports_missing_resolved_config_values(): void
    {
        config()->set('payment.stripe.secret', 'secret');
        config()->set('payment.stripe.webhook_secret', null);

        $missing = new ConfigValidator()->missing([
            'payment.stripe.secret',
            'payment.stripe.webhook_secret',
        ]);

        $this->assertSame(['payment.stripe.webhook_secret'], $missing);
    }
}
