<!-- PR title must use an allowed Conventional Commit type: feat, fix, docs, refactor, perf, test, build, ci, chore, or revert. -->

## What changed

<!-- Briefly describe the change. -->

## Why

<!-- Explain the problem, motivation, or linked issue. -->

## Verification

- [ ] `composer test`
- [ ] `composer analyse`
- [ ] `composer format:test`
- [ ] `composer validate --strict`
- [ ] Config-cache behavior checked if this change affects config loading, environment access, or deployment validation

## Consumer validation

- [ ] Required — validated against a real consumer repository
- [ ] Not required — no integration-sensitive behavior changed

<!-- If consumer validation is required, note the repository/scenario and result above or in the PR description. -->
