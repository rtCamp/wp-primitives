---
sidebar_position: 2
sidebar_label: Getting started
---

# Getting started

This guide shows the smallest complete integration: install the package, boot it
from a plugin or theme, group services in a module, and retrieve a deliberately
shared service.

## Requirements

- PHP 8.2 or newer
- WordPress 6.5 or newer
- Composer

The package has no Composer runtime dependencies beyond PHP. It is nevertheless
a WordPress library: its registration classes and most utilities call WordPress
APIs. `Encryptor` additionally requires the OpenSSL PHP extension when used.

## Install

The package is not published on public Packagist. Add the repository to the
consuming project's `composer.json` first, then require it:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/rtCamp/wp-primitives"
    }
  ]
}
```

```bash
composer require rtcamp/wp-primitives:^2.0
```

If the project is already wired to an rtCamp-hosted Composer registry that
serves this package, the `repositories` entry is unnecessary and
`composer require rtcamp/wp-primitives:^2.0` is enough on its own.

Pin with a caret constraint. `inc/Contracts/` is the public API and the project
follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html), so `^2.0`
accepts additive releases and refuses the next major. Read
[upgrading.md](upgrading.md) before moving across a major.

Composer exposes framework classes through the `rtCamp\WPPrimitives\` namespace.
The consuming plugin or theme remains responsible for requiring its own Composer
autoload file and starting its entry class.

## 1. Create a service

Use the narrowest contract that describes the service. Most hook-driven classes
only need `Registrable`:

```php
<?php
declare( strict_types = 1 );

namespace Acme\Example;

use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;

final class ContentFilters implements Registrable {
 public function register_hooks(): void {
  add_filter( 'the_content', [ $this, 'append_notice' ] );
 }

 public function append_notice( string $content ): string {
  return $content . '<p>' . esc_html__( 'Example is active.', 'acme-example' ) . '</p>';
 }
}
```

For post types, taxonomies, blocks, shortcodes, REST controllers, settings pages,
admin pages, and user roles, extend the matching `Abstract*` class instead of
repeating its registration plumbing. See [abstracts.md](abstracts.md).

## 2. Group services in a module

An `AbstractModule` is a loader for a related set of classes. Every listed class
must be constructible without required constructor arguments.

```php
<?php
declare( strict_types = 1 );

namespace Acme\Example;

use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractModule;

final class ContentModule extends AbstractModule {
 protected function get_classes(): array {
  return [
   ContentFilters::class,
  ];
 }
}
```

## 3. Bootstrap the loader

The framework deliberately does not ship a `Main` class. A plugin or theme owns
its entry point and decides when the first load begins.

### Plugin entry point

```php
<?php
/**
 * Plugin Name: Acme Example
 */

declare( strict_types = 1 );

namespace Acme\Example;

use rtCamp\WPPrimitives\Contracts\Traits\Loader;
use rtCamp\WPPrimitives\Contracts\Traits\Singleton;

require_once __DIR__ . '/vendor/autoload.php';

final class Main {
 use Loader;
 use Singleton;

 protected function __construct() {
  // Publish before loading: constructors reached during load may call Main::get_instance().
  static::$instance = $this;

  $this->load( [ ContentModule::class ] );
 }
}

Main::get_instance();
```

### Theme entry point

The same `Main` shape works in a theme. Require the theme's Composer autoloader
from `functions.php`, make the `Main` class available, and call
`Main::get_instance()` there. As in the plugin example, the constructor loads
the modules immediately. Services register their callbacks in `register_hooks()`;
work that depends on a later WordPress lifecycle event belongs in those callbacks.

The early `static::$instance = $this` assignment is important when construction
can re-enter `get_instance()`. A class using `Singleton` and its subclasses also
share one storage slot; do not call `get_instance()` on a subclass of a singleton.
See [contracts.md](contracts.md#singleton).

## Sharing a service intentionally

Classes are not retained by default. Add the `Shareable` marker only when another
class must retrieve the exact instance that was loaded:

```php
use rtCamp\WPPrimitives\Contracts\Interfaces\Shareable;
use rtCamp\WPPrimitives\Utils\Cache;

final class PluginCache extends Cache implements Shareable {
 public function __construct() {
  parent::__construct( 'acme-example' );
 }
}

final class InfrastructureModule extends AbstractModule {
 protected function get_classes(): array {
  return [ PluginCache::class ];
 }

 public function cache(): PluginCache {
  return $this->get_shared( PluginCache::class );
 }
}
```

`get_shared()` belongs to the loader that loaded the class. In this example the
cache is retrieved from `InfrastructureModule`, not from `Main`. If an outer
loader must retrieve the module itself, the module must also implement
`Shareable`.

Prefer constructor injection when objects can be assembled directly. `Shareable`
is for loader-created objects that genuinely need later retrieval; it should not
be the default for every service.

## What happens during load

For each unique class name, the loader:

1. constructs one instance;
2. calls `can_register()` for a `ConditionallyRegistrable`;
3. calls `register_hooks()` when registration is allowed;
4. stores the instance when it is `Shareable`.

Duplicate class names in one list are ignored. Each call to `load()` creates a
fresh container, so a later call on the same loader replaces the previously
shared set. Prefer one load per loader with the complete class list.

Continue with [architecture.md](architecture.md) for the lifecycle model and
[abstracts.md](abstracts.md) for implementation recipes. When something doesn't
register, start at [troubleshooting.md](troubleshooting.md).
