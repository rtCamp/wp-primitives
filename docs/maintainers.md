---
sidebar_position: 10
sidebar_label: Maintainer guide
---

# Maintainer guide

This guide covers work on `rtcamp/wp-primitives` itself. For consuming the
library, start with [getting-started.md](getting-started.md).

## Local environment

You need PHP 8.2+, Composer, Node.js, Docker, and a Docker-compatible runtime.

```bash
composer install
npm ci
npm run wp-env start
```

Composer installs host-side lint and static-analysis tools. `wp-env` provides the
real WordPress test environment; tests do not mock WordPress functions.

## Run the checks

Run these before opening a pull request:

```bash
composer lint
composer analyse
npm run test:php
```

`npm run test:php` runs PHPUnit inside the wp-env `tests-cli` container. Its
`pretest:php` hook installs Composer dependencies in that container first.

`composer test` invokes PHPUnit directly on the host. Use it only when the host
has a WordPress test suite and database configured through one of the paths
supported by `tests/bootstrap.php`, such as `WP_TESTS_DIR`. Merely starting
wp-env does not configure the host command.

Coverage can be collected in wp-env with:

```bash
npx wp-env start --xdebug=coverage
npm run test:php:coverage
```

Stop the environment when it is no longer needed:

```bash
npm run wp-env stop
```

## Test conventions

- Follow TDD: add a failing test, then implement the behavior.
- Mirror `inc/` under `tests/` for new classes and traits.
- Extend `rtCamp\WPPrimitives\Tests\TestCase` for code that calls WordPress APIs.
- A pure-logic test may extend `PHPUnit\Framework\TestCase`.
- Exercise actual WordPress registrations and registries rather than mocking
  WordPress functions.
- Add reusable test-only classes under `tests/Fixtures/`.
- Keep tests order-independent; PHPUnit runs them in random order.

CI runs PHPCS, PHPStan, and a PHP × WordPress integration-test matrix. The local
commands above are the closest single-environment equivalent.

## Changing the library

Before editing, identify which surface is affected:

- `inc/Contracts/` contains interfaces, abstracts, and traits consumed by
  plugins and themes. Signature changes here are breaking.
- `inc/` contains the container and concrete asset/render loaders.
- `inc/Utils/` contains reusable services and utilities.
- `ai/primitives-php.instructions.md` is shipped to consumers as their canonical
  framework and WordPress review guidance.
- `bin/sync-ai-instructions.js` refreshes and projects those instructions in
  consuming repositories.

For every behavior change:

1. add or update the matching test;
2. implement the smallest compatible change;
3. update the relevant consumer or maintainer documentation;
4. give the pull request a Conventional Commit title that reads as the
   changelog entry (see [Releasing](#releasing) for which type to use);
5. run lint, analysis, and integration tests.

For a change under `inc/Contracts/`, also:

- call out the compatibility impact in the pull request;
- update `ai/primitives-php.instructions.md` when consumer guidance or the
  documented contract changes;
- check every abstract subclass signature and every documented example affected
  by the change.

Do not add a package to `composer.json` `require`; production dependencies are
limited to PHP. Development-only tooling belongs in `require-dev`.

## Adding a class, interface, or trait

- Use PSR-4 paths: `rtCamp\WPPrimitives\` maps to `inc/`.
- Add `declare( strict_types = 1 );`.
- Fully type parameters and return values.
- Add `@package` and `@since` documentation.
- Use `snake_case` methods and `PascalCase` classes.
- Use `static::`, not `self::`, where late static binding is intended.
- Add a matching test in the mirrored `tests/` path.
- Add the new API to the appropriate page under `docs/` and to the README/index
  inventory when it is a new top-level capability.

New `Abstract*` classes should implement `Registrable` and expose abstract
methods only for the values consumers must supply. Reuse `Loader` and `Container`
instead of introducing another registration or service-location mechanism.

## Documentation sources

Documentation workflow pushes are filtered to `docs/**` and the documentation
workflow file. Empty commits do not trigger a push build; change a matching file
or use the workflow's manual trigger to verify deployment.

The documentation has two audiences:

- **Implementors:** `README.md` and `docs/{getting-started,architecture,contracts,abstracts,loaders,utilities}.md`.
- **Maintainers and contributors:** `CONTRIBUTING.md`, this guide, `AGENTS.md`,
  and `.github/instructions/`.

When implementation changes, search all of these locations for the affected
class or method. Source docblocks are detailed implementation references, but
they do not replace the task-oriented examples and behavior notes under `docs/`.

The AI review distribution flow is documented separately in
[ai-review-system.md](ai-review-system.md). When changing its canonical rules,
keep the approximately 4,000-character Copilot instruction limit in mind.

## Pull requests

Use the repository pull-request template. A change is ready for review when:

- lint, analysis, and integration tests pass;
- new or changed behavior has test coverage;
- compatibility implications are stated;
- user-facing documentation is current;
- the pull request title is a Conventional Commit with the right type.

## Releasing

The package is published on Packagist as `rtcamp/wp-primitives`, which reads
versions straight from git tags through the repository's Packagist webhook.
Releases are cut by [release-please](https://github.com/googleapis/release-please)
(`.github/workflows/release.yml`):

1. Pull requests are squash-merged, so each PR title becomes one commit on
   `main`. release-please reads those subjects and keeps a release pull request
   open that bumps `.release-please-manifest.json` and adds the next
   `CHANGELOG.md` section.
2. The PR title's type decides the version, so pick it from the change, not the
   files touched:

   | Change | Title |
   | --- | --- |
   | Anything breaking in `inc/Contracts/` or in documented behaviour, including a new abstract method or a new interface method | `feat!:`, major |
   | New API: a new class, an optional method with a default body, a new hook | `feat:`, minor |
   | Fixes that keep the contract | `fix:`, patch |
   | Docs, tests, CI, refactors with no behaviour change | `docs:`, `test:`, `ci:`, `refactor:`, `chore:`, no release |

   A `feat!:` also needs its entry in [upgrading.md](upgrading.md) in the same
   pull request.

   The `!` has to be in the title. The squash commit's body is built from the
   branch's commit messages, so a `BREAKING CHANGE:` footer written in the PR
   description never reaches `main`.
3. When you want to release, review the release pull request, then
   squash-merge it. release-please tags `vX.Y.Z` on the merge commit and
   publishes the GitHub release with that section as the notes.

   Don't edit the release pull request or its branch: release-please rebuilds
   both on every run, so the edit is lost. To reword an entry, edit the
   description of the merged pull request it came from and add:

   ```text
   BEGIN_COMMIT_OVERRIDE
   fix: the wording you want
   END_COMMIT_OVERRIDE
   ```

   The next release-please run (any push to `main`, or re-running the
   workflow) rebuilds the release pull request from it.
4. Confirm Packagist picked the tag up: `composer show rtcamp/wp-primitives --all`
   lists the new version within a minute or two. If it does not, check the
   webhook delivery under the repository's Settings > Webhooks.

The release pull request is opened with `GITHUB_TOKEN`, so GitHub starts no
workflow runs on it. It only changes `CHANGELOG.md` and the manifest, and the
checks ran on every commit it lists, so it is safe to merge without them.
Switching the workflow to a GitHub App token would give it its own runs.

Never push a `v*` tag or edit `.release-please-manifest.json` by hand.

Never move, delete or re-push a published tag: consumers and Packagist have
already resolved it. Fix forward with a patch release instead.

