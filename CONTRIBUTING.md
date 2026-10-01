# Contributing to wp-primitives

Thanks for your interest in improving `rtcamp/wp-primitives`. This is a
**library** — a shared PHP base consumed as a Composer package by rtCamp
WordPress plugins and themes — so its public contract (`inc/Contracts/`) is
treated as stable and changes to it are considered breaking.

## Ground rules

- **PHP 8.2+.** The package ships **zero runtime dependencies**.
- **The contract surface is the API.** Any change to an interface, abstract, or
  public signature under `inc/Contracts/` is a breaking change — call it out
  explicitly in your PR.
- **Conventions live in [AGENTS.md](AGENTS.md)** (shared across all
  contributors and AI tools). Read it before your first PR.

## Development setup

```bash
# Host tooling (PHPCS and PHPStan).
composer install

# WordPress integration tests (Docker required).
npm ci
npm run wp-env start
```

## Before you open a PR

Run all three checks locally — all of them must exit `0`:

```bash
composer lint
composer analyse
npm run test:php
```

Individual steps:

```bash
composer lint      # PHPCS against WordPress Coding Standards
composer lint:fix  # auto-fix fixable violations
composer analyse   # PHPStan static analysis
npm run test:php   # PHPUnit in the wp-env test container
```

Tests run against real WordPress via `@wordpress/env`. The `pretest:php` script
installs Composer dependencies inside the container before PHPUnit runs.
`composer test` is the lower-level host command and requires a separately
configured WordPress test suite and database; it is not the default local path.

Follow TDD: add a failing test under `tests/` (which mirrors `inc/`) first, then
the implementation. See [docs/maintainers.md](docs/maintainers.md) for test-case,
contract-change, and documentation guidance.

## Pull request checklist

- [ ] `composer lint`, `composer analyse`, and `npm run test:php` pass.
- [ ] New/changed behavior is covered by tests.
- [ ] Any change to `inc/Contracts/` is flagged as breaking in the PR description.
- [ ] Contract changes are reflected in `ai/primitives-php.instructions.md`.
- [ ] User-visible behavior is reflected in `README.md` or `docs/`.
- [ ] A `CHANGELOG.md` entry is added under `## [Unreleased]`.
- [ ] Commits follow [Conventional Commits](https://www.conventionalcommits.org/).

## License

By contributing, you agree that your contributions are licensed under the
project's [GPL-2.0-or-later](LICENSE.md) license.
