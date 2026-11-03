# Changelog

All notable changes to `rtcamp/wp-primitives` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.1.0] - 2026-10-02

### Added

- `FeatureSelector::register()` accepts a closure for a flag's `name` or
  `description` (#102). `get_features()` calls it when the metadata is read, so
  translated text is built only when it is shown.

### Changed

- `FeatureSelector::$registered` can now hold those closures. A subclass that
  reads the property directly should call `get_features()` instead, which still
  returns strings.

### Fixed

- `AbstractFeature` no longer calls `get_name()` and `get_description()` in its
  constructor (#102). It registers them as closures, so a feature that translates
  them with `__()` and is constructed before `init` no longer triggers the
  WordPress 6.7+ `_load_textdomain_just_in_time` notice. Where notices are
  displayed, as in wp-env, that notice broke wp-admin login.

## [2.0.0] - 2026-10-01

### Added

- `AbstractAbility` and `AbstractAbilityRegistrar`, base classes for the WordPress
  6.9 Abilities API (#76). Abilities default to a fail-closed `manage_options`
  permission check and opt in to REST or MCP exposure through `meta()`; a
  registrar registers its category idempotently, so several registrars can share
  one. Both are inert on WordPress older than 6.9.
- A protected `Encryptor::is_openssl_available()` seam, so the fail-closed
  "OpenSSL missing" path of `encrypt()` and `decrypt()` is covered by tests.

### Security

- `Encryptor::decrypt()` now rejects any payload shorter than the IV plus a
  full-length authentication tag. OpenSSL verifies a truncated GCM tag at its
  truncated length, so a forged 13-byte payload (IV plus one tag byte, empty
  ciphertext) decrypted to `''` for 1 in 256 tag values instead of failing.

### Fixed

- `Transients` now hashes a namespaced key longer than WordPress' 172-character
  transient name limit to `h:<md5>`. Such names were truncated by the options
  table, so the value was never found again on the next request (or, just past the
  limit, its expiry was silently dropped). Keys within the limit are unchanged.
- `AbstractBlock` registers by block name when the build directory has no
  `block.json` (a fresh clone, or before `npm run build`). It used to pass the
  directory to `register_block_type()`, which raised a "Block type names must
  contain a namespace prefix" notice on every request and registered nothing.
- `sync-ai` also finds packages in site repositories that commit only the root
  instructions projection, by detecting packages that vendor
  `rtcamp/wp-primitives`. Before, such repositories got "Nothing to do" and kept
  the legacy root file after upgrading.
- `sync-ai` prunes only the legacy files it generated and logs the real path of
  each file it removes.

### Changed

- **BREAKING: the package is now `rtcamp/wp-primitives`** (previously
  `rtcamp/wp-framework`). Require `rtcamp/wp-primitives:^2.0`. Existing installs keep
  working from their `composer.lock`, but `composer update` no longer resolves the old
  name. The new name also changes these defaults:
  - PHP namespace: `rtCamp\WPPrimitives\`.
  - `AssetLoader::HANDLE_PREFIX`: `wp-primitives-`. Default asset handles change, so
    handles referenced by string in `wp_add_inline_script()`, `wp_localize_script()`,
    dependency arrays or dequeue calls must be updated. Override the constant to keep
    the 1.x handles.
  - `ComponentLoader::get_context()`: `wp-primitives`. Subclasses that do not override
    it get `wp-primitives/component_*` hooks (`before_render`, `after_render`,
    `asset_handle`, `should_enqueue`) and `wp-primitives-component-*` asset handles.
    Override `get_context()` to keep the 1.x names.
  - Text domain of the 22 translated strings in `inc/`: `wp-primitives`. No
    translation files ship with this package; retarget any you maintain.
  - AI rules file: `ai/primitives-php.instructions.md`, synced into consumers as
    `.github/instructions/primitives-php.instructions.md`.

### Build

- Dist archives (Packagist and GitHub zipballs) no longer include `docs/`,
  `AGENTS.md`, `CONTRIBUTING.md` or `composer.lock`. `ai/` and
  `bin/sync-ai-instructions.js` still ship, because consumers run them from
  `vendor/` via `npm run sync-ai`.
- CI runs on Node 24 (Node 20 reached end of life in April 2026), PHPStan runs at
  level 6, the documentation action is pinned to a commit, and checkouts no longer
  persist credentials. Dependabot auto-merge uses GitHub's documented
  `pull_request` pattern instead of a `workflow_run` trigger with a spoofable
  `github.actor` check.

### Documentation

- Added implementor getting-started and maintainer workflow guides; corrected
  Singleton, REST-controller, compatibility, and wp-env test guidance; expanded
  loader, cache, feature-selector, timer, and utility API coverage.
- Added `docs/upgrading.md` (versioning promise and the 1.0.0 → 1.0.1 `Singleton`
  migration) and `docs/troubleshooting.md` (symptom → cause for the framework's
  exceptions, `_doing_it_wrong()` notices, and silent no-ops).
- Install docs are Packagist-first (`composer require rtcamp/wp-primitives:^2.0`),
  with a VCS `repositories` fallback for installing straight from GitHub.
- Added a worked WP-CLI example to `docs/contracts.md`, the only contract that
  had none, and a quick-look snippet to the README.
- Corrected the `Loader::load()` snippet in `docs/architecture.md` to match the
  implementation, and the `AbstractSettingsPage` capability note in
  `docs/abstracts.md` (the `option_page_capability_*` filter is unconditional).

## [1.0.1] - 2026-07-29

### Fixed

- `Singleton` returns to the ecosystem-standard storage shape:
  `protected static $instance`, stored by `get_instance()` once the constructor
  returns. The class-string-keyed private map introduced for 1.0.0 broke a real
  consumer contract — a heavy constructor assigning `static::$instance = $this;`
  first so work done during construction can re-enter `get_instance()`, which
  fataled theme-elementary on 1.0.0 with "Access to undeclared static property".
  That early-assignment guard is now the documented, tested pattern. Trade-off,
  also documented on the trait: a class using the trait and its subclasses share
  one storage slot, so do not call `get_instance()` on a subclass of a singleton.

## [1.0.0] - 2026-07-28

Initial release. Requires PHP 8.2+.

### Added

- **Loaders**
  - `AssetLoader` — registers scripts, styles and script modules from a build
    directory, reading dependencies and versions from the generated
    `*.asset.php` manifests, with a `handle()` helper for namespaced handles.
  - `TemplateLoader` — locates and renders templates across the child theme,
    parent theme and the package's own directory, honouring WordPress'
    `locate_template()` precedence, with a per-request location cache.
  - `ComponentLoader` — resolves and renders self-contained component packages
    across the same hierarchy and registers their assets on demand. Render and
    asset hooks are namespaced per loader context, so one package's listeners
    never fire for another's.
- **Composition**
  - `Container` and the `Loader` trait — instantiate a list of classes, register
    hooks for anything `Registrable`, and cache anything `Shareable`.
  - Contracts: `Registrable`, `ConditionallyRegistrable`, `Shareable`,
    `CLICommand`.
  - `Singleton` trait, storing one instance per concrete class so a parent and
    subclass never share one.
- **Abstract base classes** — `AbstractModule`, `AbstractFeature`,
  `AbstractBlock`, `AbstractPostType`, `AbstractTaxonomy`, `AbstractUserRole`,
  `AbstractShortcode`, `AbstractAdminPage`, `AbstractSettingsPage` and
  `AbstractRESTController`.
- **Utilities**
  - `Cache` — object-cache wrapper with group namespacing and opt-in
    stale-while-revalidate.
  - `Encryptor` — authenticated encryption (AES-256-GCM by default), injectable
    per key domain, with a `key()` seam for sourcing secrets from a KMS or
    environment. Secrets of any length are derived to a full cipher-length key.
  - `FeatureSelector` and `FeatureSelectorSettingsPage` — feature flags stored in
    a single option, overridable by PHP constants for hard locks.
  - `Transients` — prefix-namespaced transient access.
  - `Timer` — named, multi-scope timing.
  - `Logger` — levelled logging.
- Reference documentation under `docs/`, a GPL-2.0-or-later `LICENSE.md`, and a
  WordPress integration test suite running against `@wordpress/env`.

[Unreleased]: https://github.com/rtCamp/wp-primitives/compare/v2.1.0...HEAD
[2.1.0]: https://github.com/rtCamp/wp-primitives/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/rtCamp/wp-primitives/compare/v1.0.1...v2.0.0
[1.0.1]: https://github.com/rtCamp/wp-primitives/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/rtCamp/wp-primitives/releases/tag/v1.0.0
