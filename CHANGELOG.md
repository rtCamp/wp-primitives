# Changelog

All notable changes to `rtcamp/wp-primitives` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **BREAKING: renamed the package from `rtcamp/wp-framework` to `rtcamp/wp-primitives`.**
  The PHP namespace moves from `rtCamp\WPFramework\` to `rtCamp\WPPrimitives\`, the
  repository moves to `rtCamp/wp-primitives`, and the AI rules file shipped to consumers
  is renamed from `ai/framework-php.instructions.md` to `ai/primitives-php.instructions.md`
  (synced into consumers as `.github/instructions/primitives-php.instructions.md`).
  Consumers must move to a `^2.0` constraint; `^1.0` continues to resolve from the
  existing `v1.0.0` / `v1.0.1` tags, which keep the old package name.
  Reason: `10up/wp-framework` occupies the same name in the same category on Packagist.

### Documentation

- Added implementor getting-started and maintainer workflow guides; corrected
  Singleton, REST-controller, compatibility, and wp-env test guidance; expanded
  loader, cache, feature-selector, timer, and utility API coverage.
- Added `docs/upgrading.md` (versioning promise and the 1.0.0 → 1.0.1 `Singleton`
  migration) and `docs/troubleshooting.md` (symptom → cause for the framework's
  exceptions, `_doing_it_wrong()` notices, and silent no-ops).
- Documented the real install path: the package is not on public Packagist, so
  the consumer needs a VCS `repositories` entry and a `^1.0` constraint.
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

[Unreleased]: https://github.com/rtCamp/wp-primitives/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/rtCamp/wp-primitives/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/rtCamp/wp-primitives/releases/tag/v1.0.0
