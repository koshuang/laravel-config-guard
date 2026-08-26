# Laravel Config Guard

Guardrails for Laravel configuration contracts.

Laravel Config Guard treats environment variables as **deployment inputs** and Laravel `config()` as the **application-facing contract**.

It helps answer two questions:

- Before merge: is the application's configuration contract internally consistent?
- Before startup: does this deployment satisfy that contract?

## Why

Laravel already provides the right primitives for configuration:

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

The problem is usually not reading configuration. The problem is keeping the contract consistent over time.

Common failure modes include:

- application code calling `env()` directly;
- a new application-owned environment variable being added to `config/*.php` but omitted from `.env.example`;
- duplicate keys in checked-in env files;
- production deployments starting with required resolved configuration missing.

Laravel Config Guard turns those cases into explicit CI or deployment failures.

## Real-world example

A complete consumer integration is available in [`koshuang/laravel-hexagonal-architecture`](https://github.com/koshuang/laravel-hexagonal-architecture).

That project uses Laravel Config Guard for a real application-owned setting: the Account module's maximum money-transfer threshold.

The integration keeps the configuration boundary explicit:

```text
ACCOUNT_MAXIMUM_TRANSFER_THRESHOLD
              ↓
       config/transfer.php
              ↓
        Infrastructure DI
              ↓
   MoneyTransferProperties
              ↓
       SendMoneyService
```

The application layer never reads `env()` or Laravel `config()` directly. The environment variable is adapted into Laravel configuration first, then the Infrastructure layer validates and injects the resolved value into the application service.

The example also enables all current lint rules:

```php
'lint' => [
    'env_outside_config' => true,
    'missing_example_keys' => true,
    'duplicate_env_keys' => true,
],
```

and declares the application-owned config plus required resolved value:

```php
'application_config' => [
    'config/transfer.php',
],

'required' => [
    'production' => [
        'transfer.maximum_transfer_threshold',
    ],
],
```

Its CI runs both commands:

```bash
php artisan config:lint
php artisan config:validate
```

This means pull requests fail if application code starts using `env()` outside `config/`, an application-owned env key is missing from `.env.example`, a configured env file contains duplicate keys, or required resolved configuration is missing.

The integration was also used as the consumer smoke test for `v0.1.0`, exercising package auto-discovery, Git-based Composer installation, configuration linting, deployment validation, static analysis, coding standards, architecture validation, and the application's test suite together.

See the implementation in [`koshuang/laravel-hexagonal-architecture`](https://github.com/koshuang/laravel-hexagonal-architecture) and the original integration PR, [`#12`](https://github.com/koshuang/laravel-hexagonal-architecture/pull/12).

## Installation

```bash
composer require koshuang/laravel-config-guard
```

Install Laravel Config Guard as a regular dependency when `config:validate` runs in the production artifact. This keeps the deployment command available when production dependencies are installed with `composer install --no-dev`.

The package uses Laravel package auto-discovery.

Publish the configuration file when you need to customize linting or deployment validation:

```bash
php artisan vendor:publish --tag=config-guard-config
```

This creates:

```text
config/config-guard.php
```

## Quick start

### 1. Keep `env()` in `config/`

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

Application code should consume resolved config:

```php
config('payment.stripe.secret');
```

not raw environment input:

```php
env('STRIPE_SECRET');
```

### 2. Declare application-owned config files

```php
// config/config-guard.php
return [
    'application_config' => [
        'config/payment.php',
        'config/order-import.php',
    ],
];
```

Environment variables referenced by those files should be discoverable in `.env.example`:

```dotenv
STRIPE_ENABLED=true
STRIPE_SECRET=
STRIPE_TIMEOUT=30
```

### 3. Run the CI lint gate

```bash
php artisan config:lint
```

### 4. Declare required deployment configuration

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

### 5. Validate after Laravel config is resolved

```bash
php artisan config:cache
php artisan config:validate
```

A missing required value returns a non-zero exit code so deployment can fail before the application receives traffic.

## Configuration reference

The published configuration looks like this:

```php
return [
    'lint' => [
        'env_outside_config' => true,
        'missing_example_keys' => true,
        'duplicate_env_keys' => true,
    ],

    'application_config' => [
        // 'config/payment.php',
        // 'config/order-import.php',
    ],

    'env_files' => [
        '.env.example',
        '.env.testing',
    ],

    'required' => [
        'production' => [
            // 'app.key',
            // 'database.connections.mysql.host',
        ],
    ],
];
```

### `lint.env_outside_config`

When enabled, `config:lint` fails when `env()` is referenced outside `config/`.

This enforces the boundary:

```text
env() -> config/*.php -> config() -> application code
```

### `lint.missing_example_keys`

When enabled, environment variables referenced by files in `application_config` must also exist in `.env.example`.

This rule is intentionally scoped to **application-owned** config files. Laravel and third-party packages expose optional env overrides that do not belong in every application's configuration contract.

`.env.example` is therefore treated as a discoverable catalog of the application's intended environment surface, not as a universal list of every env variable Laravel can read.

Literal env references using either positional or named arguments are supported:

```php
env('STRIPE_SECRET');
env(key: 'STRIPE_SECRET');
```

### `lint.duplicate_env_keys`

When enabled, configured env files may not contain the same key more than once.

Laravel Config Guard does not define precedence for duplicate keys. Duplicate configuration is considered ambiguous and invalid.

### `application_config`

List config files that represent configuration owned by your application:

```php
'application_config' => [
    'config/payment.php',
    'config/order-import.php',
],
```

Only these files participate in the `.env.example` completeness check.

### `env_files`

Files checked for duplicate keys:

```php
'env_files' => [
    '.env.example',
    '.env.testing',
],
```

Add other checked-in env files if your repository uses them.

### `required`

Declare resolved Laravel config keys required by environment:

```php
'required' => [
    'production' => [
        'app.key',
        'database.connections.mysql.host',
        'payment.stripe.secret',
    ],
],
```

The keys are Laravel config paths, not raw env variable names.

## Commands

### `config:lint`

```bash
php artisan config:lint
```

Checks the repository-level configuration contract.

Current rules:

- `env()` may only be used under `config/`;
- application-owned env keys must exist in `.env.example`;
- configured env files may not contain duplicate keys.

You can lint a different Laravel project root with:

```bash
php artisan config:lint --path=/path/to/project
```

When `--path` is supplied, Laravel Config Guard loads that target project's `config/config-guard.php` and merges it over the package defaults. The target project's `application_config`, `env_files`, and lint toggles therefore control the scan instead of the currently booted application's settings.

Example success:

```text
Configuration contract is valid.
```

Example failures:

```text
env() used outside config/: app/Services/Checkout.php
```

```text
STRIPE_SECRET is referenced by application config but missing from .env.example
```

```text
.env.example contains duplicate key STRIPE_SECRET on lines 10, 24
```

Any lint failure returns a non-zero exit code.

### `config:validate`

```bash
php artisan config:validate
```

Validates the required config keys declared for the current Laravel environment.

Example:

```php
'required' => [
    'production' => [
        'payment.stripe.secret',
    ],
],
```

Success:

```text
✓ payment.stripe.secret
Required configuration is valid.
```

Failure:

```text
✗ payment.stripe.secret
Required configuration is missing.
```

Validation failure returns a non-zero exit code.

## Why validate Laravel config instead of raw env

This distinction is important.

Consider:

```php
// config/payment.php
return [
    'stripe' => [
        'timeout' => env('STRIPE_TIMEOUT', 30),
    ],
];
```

A production environment without `STRIPE_TIMEOUT` can still be valid because Laravel resolves:

```php
config('payment.stripe.timeout') === 30;
```

A raw env validator would incorrectly treat the missing environment variable as the thing being validated.

Laravel Config Guard validates the **resolved application configuration** instead:

```text
deployment input
      ↓
   env()
      ↓
config/*.php + defaults
      ↓
 resolved config()
      ↓
 deployment validation
```

This makes defaults, environment-specific composition, and Laravel's config cache part of the actual contract.

## CI usage

A minimal GitHub Actions step:

```yaml
- name: Validate configuration contract
  run: php artisan config:lint
```

A typical application CI pipeline can run the lint gate alongside tests and static analysis:

```yaml
- run: composer install --prefer-dist --no-interaction
- run: php artisan config:lint
- run: vendor/bin/phpunit
```

The command's non-zero exit status makes it usable with GitHub Actions, CircleCI, GitLab CI, or any other CI system.

## Deployment usage

Run deployment validation after environment values have been injected and Laravel configuration has been resolved:

```bash
php artisan config:cache
php artisan config:validate
```

Conceptually:

```text
environment injected
        ↓
php artisan config:cache
        ↓
php artisan config:validate
        ↓
start application / receive traffic
```

The intended failure mode is **deployment failure**, not a runtime exception reached later on a user request.

## `.env.example` philosophy

`.env.example` provides **discoverability**. It is not treated as a complete validation schema.

That means:

- application-owned env keys should be represented there;
- secret values may be empty placeholders;
- values with safe defaults may still be listed for discoverability;
- Laravel/framework optional overrides do not all need to be copied into it;
- production requiredness is expressed through resolved Laravel config validation instead.

Example:

```dotenv
STRIPE_ENABLED=true
STRIPE_SECRET=
STRIPE_TIMEOUT=30
```

This documents the intended deployment surface without pretending `.env.example` can express every runtime constraint.

## Non-goals

Laravel Config Guard intentionally does **not**:

- manage secrets;
- replace Laravel's configuration system;
- replace `env()` or `config()`;
- integrate directly with AWS Secrets Manager, Vault, or another secret backend;
- require every Laravel/framework env override to appear in `.env.example`;
- automatically edit or reorder `.env.example`;
- define precedence for duplicate env keys.

The package is a configuration **contract linter and deployment validator**, not a configuration store.

## Development

Install dependencies:

```bash
composer install
```

Run tests:

```bash
composer test
```

Run static analysis:

```bash
composer analyse
```

Static analysis uses Larastan / PHPStan at level 9.

Check formatting:

```bash
composer format:test
```

Apply Laravel Pint formatting:

```bash
composer format
```

The repository CI currently validates:

- Laravel 12 and 13;
- PHP 8.2, 8.3, and 8.4 where compatible;
- PHPUnit / Orchestra Testbench;
- Larastan / PHPStan level 9;
- Laravel Pint;
- a minimum 85% line coverage gate.

## Compatibility

Laravel 12 and 13 on PHP 8.2+ subject to each Laravel version's own PHP requirements.

## Design principles

Laravel Config Guard is built around a few rules:

1. Environment variables are deployment input.
2. `env()` belongs at the configuration boundary.
3. `config()` is the application interface.
4. `.env.example` is discovery, not runtime validation.
5. Duplicate environment configuration is invalid ambiguity.
6. Required production configuration is validated after Laravel resolves config.
7. Missing required configuration should fail deployment before traffic.

## License

MIT
