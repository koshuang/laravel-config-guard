# Contributing

Thanks for contributing to `koshuang/laravel-config-guard`.

This guide is the human-oriented entry point for local development, pull requests, and releases. Project design rules, compatibility constraints, testing expectations, and implementation guidance live in [`AGENTS.md`](AGENTS.md); treat that file as the canonical source when this guide intentionally stays high level.

## Local setup

Requirements are defined by [`composer.json`](composer.json). Use a PHP version supported by the package and Composer 2.

Clone the repository, then install dependencies:

```bash
git clone https://github.com/koshuang/laravel-config-guard.git
cd laravel-config-guard
composer install
```

If you are working on compatibility-sensitive changes, also review the supported PHP/Laravel/Testbench combinations in `composer.json` and `.github/workflows/tests.yml` rather than copying those version details into new documentation.

## Local verification

Before opening or updating a pull request, run the checks relevant to your change. The normal baseline is:

```bash
composer validate --strict
composer test
composer analyse
composer format:test
npx markdownlint-cli2@0.23.2 "**/*.md"
```

These commands cover Composer metadata validation, the PHPUnit test suite, PHPStan/Larastan static analysis, Pint formatting checks, and Markdown linting. The Markdown command requires Node.js/npm and uses the repository-level `.markdownlint-cli2.jsonc` configuration.

CI also enforces at least **85% line coverage**. Changes to executable behavior should include enough tests to keep the repository at or above that threshold. Prefer behavior and regression coverage over tests that only mirror implementation details.

For the complete testing and static-analysis expectations, see [`AGENTS.md`](AGENTS.md).

## Pull requests

Keep changes focused and include tests or documentation when the public behavior changes.

PR titles must follow a Conventional Commit-style format because the title is expected to survive squash merge into `main` and becomes part of the release history.

Allowed types are:

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
docs: add contributor guide
fix: preserve cached config validation semantics
feat: support additional config roots
```

A scope is optional.

## Consumer-repository validation

The package test suite is the default validation boundary, but integration-sensitive changes may also require validation in a real Laravel consumer repository.

Run consumer-repo validation when a change could affect framework lifecycle or package integration behavior that package-level tests may not fully represent, such as configuration loading/caching, service-provider bootstrapping, package discovery, Artisan command integration, or deployment validation semantics.

The current reference consumer repository and the detailed criteria for high-risk integration changes are documented in [`AGENTS.md`](AGENTS.md). Do not treat consumer validation as a substitute for the package test suite.

## Releases

Releases are automated with Release Please. Do not manually create routine version bumps, changelog entries, tags, or GitHub Releases.

After changes are merged to `main`, Release Please uses the Conventional Commit history to maintain a release PR. When that release PR is merged, Release Please manages:

- `CHANGELOG.md`
- the next SemVer version
- the `vX.Y.Z` Git tag
- the GitHub Release and generated release notes

The workflow and release configuration are defined in `.github/workflows/release-please.yml`, `release-please-config.json`, and `.release-please-manifest.json`. Refer to those files for implementation details that may evolve over time.

## Project conventions

Before making a non-trivial code change, read [`AGENTS.md`](AGENTS.md). It defines the package's scope boundaries, Laravel package design constraints, config-cache semantics, compatibility expectations, testing requirements, and definition of done.

When documentation and implementation disagree, update the documentation together with the implementation instead of adding another source of truth.
