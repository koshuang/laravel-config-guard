# Laravel Config Guard

Guardrails for Laravel configuration contracts.

Laravel Config Guard treats environment variables as deployment inputs and Laravel `config()` as the application-facing contract.

## Install

```bash
composer require --dev koshuang/laravel-config-guard
```

Publish the configuration when you need deployment validation rules:

```bash
php artisan vendor:publish --tag=config-guard-config
```

## CI lint

```bash
php artisan config:lint
```

The initial rules check that:

- `env()` is only used under `config/`.
- Environment variables referenced by application config exist in `.env.example`.
- Configured env files do not contain duplicate keys.

Example:

```php
// config/payment.php
return [
    'stripe' => [
        'enabled' => env('STRIPE_ENABLED', true),
        'secret' => env('STRIPE_SECRET'),
        'timeout' => env('STRIPE_TIMEOUT', 30),
    ],
];
```

```dotenv
STRIPE_ENABLED=true
STRIPE_SECRET=
STRIPE_TIMEOUT=30
```

Application code should consume resolved config:

```php
config('payment.stripe.secret');
```

not raw environment input:

```php
env('STRIPE_SECRET');
```

## Deployment validation

Declare config keys that must resolve to non-empty values in a given environment:

```php
// config/config-guard.php
return [
    'required' => [
        'production' => [
            'app.key',
            'database.connections.mysql.host',
            'payment.stripe.secret',
            'payment.stripe.webhook_secret',
        ],
    ],
];
```

Then use the command as a deployment gate after configuration is resolved:

```bash
php artisan config:cache
php artisan config:validate
```

A missing required config value returns a non-zero exit code so the deployment can fail before the application receives traffic.

## Philosophy

```text
.env / container env / secret manager
                 ↓
               env()
                 ↓
            config/*.php
                 ↓
              config()
                 ↓
         application code
```

`.env.example` provides discoverability. Laravel config provides the application boundary. Deployment validation verifies that the current environment satisfies the contract.

## License

MIT
