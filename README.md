<h1 align="center">wp-primitives</h1>

<p align="center">
  <a href="LICENSE.md"><img src="https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg" alt="License: GPL-2.0-or-later"></a>
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777bb4.svg" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/runtime%20dependencies-0-brightgreen.svg" alt="Zero runtime dependencies">
</p>

<p align="center">
  A shared PHP base for WordPress projects, distributed as a Composer package
  (<code>rtcamp/wp-primitives</code>). It ships the contracts, loaders, and
  utilities that plugins and themes boot through — so you write intent instead
  of registration boilerplate.
</p>

---

`wp-primitives` is a **library, not a plugin**. It ships contracts (interfaces,
abstracts, traits) plus concrete loaders and utilities; consuming plugins and
themes build their features on top. It has **zero Composer runtime dependencies**.

Requirements:

- PHP 8.2+
- WordPress 6.5+
- Composer
- The OpenSSL PHP extension when using `Encryptor`

## Install

Not on public Packagist — add the repository to the consuming project's
`composer.json`, then require it with a caret constraint:

```json
{
	"repositories": [
		{ "type": "vcs", "url": "https://github.com/rtCamp/wp-primitives" }
	]
}
```

```bash
composer require rtcamp/wp-primitives:^1.0
```

(The `repositories` entry is unnecessary when the project already resolves this
package through an rtCamp-hosted Composer registry.)

PSR-4 autoloading: `rtCamp\WPPrimitives\` → `inc/`.

## Quick look

```php
use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractPostType;
use rtCamp\WPPrimitives\Contracts\Traits\Loader;
use rtCamp\WPPrimitives\Contracts\Traits\Singleton;

final class ArticlePostType extends AbstractPostType {
	public static function get_slug(): string    { return 'article'; }
	public function get_singular_label(): string { return __( 'Article', 'acme' ); }
	public function get_plural_label(): string   { return __( 'Articles', 'acme' ); }
	public function get_menu_icon(): string      { return 'dashicons-media-document'; }
}

final class Main {
	use Loader;
	use Singleton;

	protected function __construct() {
		static::$instance = $this;   // before loading classes that can re-enter
		$this->load( [ ArticlePostType::class ] );   // instantiates + registers hooks
	}
}

Main::get_instance();
```

A registered, REST-enabled post type with no `register_post_type()` call and no
`init` hook written by hand. Full walkthrough in
[docs/getting-started.md](docs/getting-started.md).

## What's inside

- **Registration core** — the spine every consumer boots through:
  - `Registrable`, `ConditionallyRegistrable`, `Shareable`, `CLICommand`
    interfaces
  - the `Loader` trait (instantiate a list of classes, register their hooks,
    cache the shared ones) and the `Container` it stores instances in
- **Twelve `Abstract*` base classes** — one per WordPress registration chore, so
  a consumer writes intent instead of boilerplate: `AbstractModule`,
  `AbstractPostType`, `AbstractTaxonomy`, `AbstractBlock`, `AbstractShortcode`,
  `AbstractRESTController`, `AbstractSettingsPage`, `AbstractAdminPage`,
  `AbstractUserRole`, `AbstractFeature`, `AbstractAbility`,
  `AbstractAbilityRegistrar`
- **Asset & render loaders** — `AssetLoader` (scripts/styles/modules +
  `*.asset.php` manifests), `ComponentLoader` and `TemplateLoader` (resolve
  components/templates across the child-theme → parent-theme → package hierarchy)
- **`Singleton` trait** — standard `get_instance()` with clone/wakeup guards
- **Utilities & services** (`inc/Utils/`) — context-scoped helpers:
  - `Encryptor` — authenticated AES-256-GCM encryption for values stored in the DB
  - `Cache` — typed wrapper over the WP object cache, group-namespaced, optional SWR
  - `FeatureSelector` + `FeatureSelectorSettingsPage` — a fail-closed feature-flag
    registry and its admin toggle page
  - `Logger` — context-prefixed, `WP_DEBUG`-gated logging
  - `Timer` — named request-scoped timers and laps
  - `Transients` — prefix-namespaced transient storage

The contract surface (`inc/Contracts/`) is the public API: every interface,
abstract, and signature there is consumed by dependents, so changes to it are
treated as breaking.

## What's NOT here (intentional)

- **No bootstrap / `Main` class.** The framework gives you the `Loader` trait and
  the contracts; each consumer writes its own entry class that kicks off the first
  `load()`. The framework is the spine, not the application.
- **No project scaffolding engine.** Scaffold/init tooling lives in a separate
  package (`@rtcamp/wp-tooling`), not here.

## Documentation

Start with [docs/index.md](docs/index.md), then:

| Doc | What it covers |
|---|---|
| [getting-started.md](docs/getting-started.md) | Install, bootstrap a plugin or theme, load a module, and share a service. |
| [architecture.md](docs/architecture.md) | How a class becomes a live hook — the `Registrable` → `Loader` → `Container` flow. Read first. |
| [contracts.md](docs/contracts.md) | The interfaces and traits in detail. |
| [abstracts.md](docs/abstracts.md) | Cookbook for the `Abstract*` base classes. |
| [loaders.md](docs/loaders.md) | `AssetLoader`, `ComponentLoader`, `TemplateLoader` and the theme-override hierarchy. |
| [utilities.md](docs/utilities.md) | `Encryptor`, `Cache`, feature flags, logging, transients, timers, and `Container`. |
| [upgrading.md](docs/upgrading.md) | What changes between releases and what a consumer has to do about it. |
| [troubleshooting.md](docs/troubleshooting.md) | Symptom → cause for the errors and silent no-ops the framework emits. |
| [ai-review-system.md](docs/ai-review-system.md) | How the AI review instructions are authored here and synced into the skeletons. |
| [maintainers.md](docs/maintainers.md) | Development environment, tests, change checklist, and documentation maintenance. |

## Development

```bash
composer install
npm ci
npm run wp-env start
composer lint
composer analyse
npm run test:php
```

Tests run against real WordPress via [`@wordpress/env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/).
Use `npm run test:php`, which runs PHPUnit inside the wp-env test container;
`composer test` only works directly when a host WordPress test suite has been
configured. See [docs/maintainers.md](docs/maintainers.md) for the complete
workflow.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

[GPL-2.0-or-later](LICENSE.md) © rtCamp

<p align="center">
  <a href="https://rtcamp.com"><img src="https://n8e0ka87m9.gdcdn.us/kfnbt046p8/GitHub_Banner.webp" alt="rtCamp" width="100%"></a>
</p>
