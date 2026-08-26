# AGENTS.md

This file is the canonical set of instructions for AI coding agents working on `koshuang/laravel-config-guard`.

## Project purpose

`laravel-config-guard` is a Laravel configuration contract linter and deployment validator.

It answers two questions:

1. Before merge: **Is the application's configuration contract internally consistent?**
2. Before startup/deploy: **Does the resolved Laravel configuration satisfy the deployment contract?**

Keep the package focused on configuration contracts. It is not a secret manager, environment-file generator, or general configuration framework.

## Core configuration model

Treat configuration as this pipeline:

```text
Environment / deployment input
        ↓
      env()
        ↓
   config/*.php
        ↓
     config()
        ↓
 Application code
```

The design rules are:

- Environment variables are deployment inputs, not the application API.
- `env()` belongs in Laravel configuration files only.
- Application code should consume `config()` rather than call `env()` directly.
- `.env.example` is a discoverability/catalog mechanism, not a typed schema.
- Required deployment configuration is validated from Laravel's **resolved configuration**, including when configuration is cached.
- Exact duplicate environment keys are errors. Do not introduce precedence rules for duplicates.
- Application-owned environment variables should be discoverable in `.env.example`.
- Framework/package optional overrides should not be forced into `.env.example` unless they are part of the application's own contract.

## Scope boundaries

Do not add these capabilities without an explicit product decision:

- automatic `.env` editing or generation
- secret storage or secret rotation
- AWS Secrets Manager / SSM integration
- replacing Laravel's `env()` behavior
- a configuration schema DSL
- implicit precedence between duplicate keys

Prefer a small, composable guardrail over turning this package into a deployment platform.

## Laravel package design guidelines

Follow normal Laravel package conventions and preserve compatibility with Laravel's lifecycle.

- Keep package bootstrapping in the service provider small and deterministic.
- Use Laravel package auto-discovery through `composer.json`.
- Keep publishable/default package configuration under `config/`.
- Do not read raw `.env` values during normal application runtime when resolved `config()` is sufficient.
- Code that validates deployment state must work correctly with `php artisan config:cache`.
- Avoid assumptions that configuration files are evaluated on every request or command.
- Use Laravel's container for framework integration and keep low-level parsing/scanning logic independently testable where practical.
- Artisan commands should have stable names and machine-meaningful exit codes.
- Avoid side effects during package registration/boot that would surprise consumer applications.
- Do not require consumers to modify application code merely to satisfy package internals unless it is part of the documented public contract.
- Treat public configuration keys, command names, output relied on by CI, and documented behavior as compatibility-sensitive APIs.

## Supported runtime and tooling

Current package constraints are defined by `composer.json` and CI. Do not silently narrow them.

At the time of writing:

- PHP: `^8.2`
- Laravel components: `^12.0|^13.0`
- Orchestra Testbench: `^10.0|^11.0`
- PHPUnit: `^11.0|^12.0`
- PHPStan/Larastan: level 9
- Pint for formatting

When changing dependency constraints, update CI coverage and explain the compatibility impact in the PR.

## Existing package mechanisms

Preserve and extend these mechanisms rather than bypassing them.

### Linting

`php artisan config:lint` checks configuration-contract consistency. Current important rules include:

- `env()` outside allowed config locations
- application-owned env keys missing from `.env.example`
- duplicate exact keys in configured environment files

Application-owned config discovery is controlled by:

```php
config('config-guard.application_config', [])
```

Configured environment files are controlled by:

```php
config('config-guard.env_files', [])
```

### Deployment validation

`php artisan config:validate` validates required **resolved Laravel config keys**, not raw environment-variable presence.

Required keys are environment-specific through `config-guard.required`.

This distinction is intentional. Do not change validation to inspect raw env values unless the public contract is explicitly redesigned.

### Config cache semantics

The test suite contains explicit regression coverage using Laravel's real `config:cache` command.

Any change involving config loading, environment access, or deployment validation must preserve both behaviors:

- values resolved when config cache is created remain authoritative even if the raw environment changes later
- values missing when config cache is created remain missing even if the raw environment is populated later

Do not mock away this behavior in regression coverage.

## Testing requirements

Use Orchestra Testbench for Laravel integration tests.

For behavior changes:

- add or update a positive-path test
- add a failure/negative-path test when the feature can fail
- add a regression test for every bug fix when feasible
- prefer testing public behavior and command exit codes over implementation details
- use Laravel's real configuration lifecycle when the behavior depends on framework semantics
- clean up temporary files, cached config, environment mutations, and global state in `finally`/teardown paths
- keep tests deterministic and isolated

For configuration scanning/parsing changes, include representative edge cases such as quoting, named arguments, duplicate keys, and path boundaries when relevant.

For high-risk integration changes, consider validating against the real consumer repository used for the initial smoke test: `koshuang/laravel-hexagonal-architecture`.

A green package test suite is necessary but does not by itself prove consumer compatibility.

## Required local verification

Before declaring a change complete, run the checks relevant to the change. For normal code changes, the expected baseline is:

```bash
composer validate --strict
composer test
composer analyse
composer format:test
```

For changes that affect coverage-sensitive code, ensure the repository coverage gate still passes. CI enforces at least **85% line coverage**.

If a command cannot be run, state that explicitly instead of claiming completion.

## Static analysis and type safety

PHPStan/Larastan level 9 is a design constraint, not a nuisance to suppress.

- Do not add broad ignores just to make CI green.
- Narrow `mixed` values at framework boundaries before using them.
- Validate external/configuration values before casting when a cast could silently change invalid input.
- Prefer explicit runtime validation where invalid deployment data would otherwise be coerced.
- Use precise PHPDoc only where the framework or test harness cannot express the type adequately.

## Code style

- Follow Laravel/PHP conventions already present in the repository.
- Run Pint; do not hand-format against the formatter.
- Keep classes and methods focused.
- Prefer clear domain names over generic helpers.
- Avoid speculative abstractions and premature extension points.
- Keep framework-specific behavior at clear boundaries when practical.

## Backward compatibility and SemVer

Treat these as public compatibility surfaces:

- Artisan command names and exit semantics
- published config shape and config keys
- documented lint rules
- validation semantics
- supported PHP/Laravel versions
- package auto-discovery behavior

Use SemVer intentionally:

- `fix:` for backward-compatible fixes
- `feat:` for backward-compatible features
- breaking behavior requires an explicit breaking-change signal and migration guidance

For pre-1.0 releases, still treat consumer-facing changes carefully; do not assume breakage is harmless just because the major version is `0`.

## Git, PR, and release workflow

PR titles are validated as Conventional Commits and are expected to survive squash merge into `main`.

Allowed types:

```text
feat
fix
docs
refactor
perf
test
build
ci
chore
revert
```

Examples:

```text
feat: support additional config roots
fix: preserve cached config validation semantics
test: cover duplicate env keys
docs: clarify deployment validation
```

Release Please runs on pushes to `main` and uses Conventional Commit history to maintain a Release PR.

Release Please is configured to:

- maintain `CHANGELOG.md`
- calculate the next SemVer version
- create `vX.Y.Z` tags
- create GitHub Releases from generated changelog content

Do not manually edit release tags or generated release metadata as part of ordinary feature work unless the release workflow specifically requires recovery.

## Agent workflow

When implementing a task:

1. Read `README.md`, relevant source/tests, `composer.json`, and applicable workflows before changing behavior.
2. Identify whether the request changes the public contract or only implementation details.
3. Make the smallest coherent change that satisfies the request.
4. Add regression/behavior tests before or with the implementation.
5. Run formatting, tests, static analysis, and Composer validation.
6. Update README/documentation when user-facing behavior, commands, config, or release workflow changes.
7. Use a Conventional Commit-compatible PR title.
8. Do not describe work as done until automated evidence supports it.

## Definition of done

A code change is not done merely because the implementation looks correct.

For ordinary changes, completion evidence should include the applicable combination of:

- tests passing
- PHPStan/Larastan passing
- Pint passing
- Composer validation passing
- coverage gate passing
- review findings resolved
- real consumer validation when integration risk warrants it

Prefer evidence over assumptions, especially for Laravel lifecycle behavior such as configuration caching, package discovery, service-provider bootstrapping, and consumer integration.

## Security and secrets

- Never commit real secrets, tokens, credentials, or production values.
- Test environment variables must use obviously fake values.
- Do not turn `.env.example` into a secret store.
- Do not add secret-manager integration under the guise of configuration validation without explicit scope approval.

## Documentation discipline

Keep `README.md` aligned with actual implemented behavior.

Do not claim support for features that are only planned or inferred. In particular, distinguish between:

- configuration presence/requiredness validation
- type/range/schema validation

The current package is focused on the former unless the implementation explicitly evolves.
