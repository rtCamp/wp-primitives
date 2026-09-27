---
sidebar_position: 4
sidebar_label: Contracts
---

# Contracts — interfaces & traits

This is the reference for the small set of interfaces and traits under
`inc/Contracts/`. They are the public vocabulary of the registration system from
[architecture.md](architecture.md). Everything here is **public API**: a renamed
method or changed signature breaks every skeleton, so treat changes as breaking.

## Interfaces

### `Registrable`

[`inc/Contracts/Interfaces/Registrable.php`](../inc/Contracts/Interfaces/Registrable.php)

The base marker for anything with WordPress hooks to register.

```php
interface Registrable {
    public function register_hooks(): void;
}
```

`register_hooks()` is where a class calls its `add_action()` / `add_filter()`.
The `Loader` calls this method for you during `load()`; you never call it
directly. Every `Abstract*` class implements this.

### `ConditionallyRegistrable`

[`inc/Contracts/Interfaces/ConditionallyRegistrable.php`](../inc/Contracts/Interfaces/ConditionallyRegistrable.php)

A `Registrable` whose registration is gated on a runtime check.

```php
interface ConditionallyRegistrable extends Registrable {
    public function can_register(): bool;
}
```

When the `Loader` meets one of these, it calls `can_register()` first and skips
`register_hooks()` entirely when it returns `false`. Use it for a feature behind
a flag, a WP-CLI-only service (`defined( 'WP_CLI' )`), an admin-only service
(`is_admin()`), and so on — instead of scattering early-returns inside
`register_hooks()`.

```php
final class CliOnlyService implements ConditionallyRegistrable {
    public function can_register(): bool {
        return defined( 'WP_CLI' ) && WP_CLI;
    }
    public function register_hooks(): void { /* … */ }
}
```

### `Shareable`

[`inc/Contracts/Interfaces/Shareable.php`](../inc/Contracts/Interfaces/Shareable.php)

A pure **marker** interface — no methods.

```php
interface Shareable {}
```

When the `Loader` meets a `Shareable`, it caches the instance in its `Container`
so it can be retrieved later via `get_shared( ClassName::class )`. Absence of the
marker means a fresh, non-shared instance — the default.

The interface's own docblock calls it a **soft anti-pattern**: it introduces
hidden shared state, much like a singleton. Reach for it only when an object
genuinely has to be fetched again elsewhere — either a hooked class that must
also be shared, or a plain shared service on its own (the loaders'
`AssetLoader`/`ComponentLoader` sharing is the canonical legitimate use, and those
are plain, non-`Registrable` services). If you can inject the object instead, do that.

### `CLICommand`

[`inc/Contracts/Interfaces/CLICommand.php`](../inc/Contracts/Interfaces/CLICommand.php)

The shape of a WP-CLI command. All three methods are **static** — a command is a
description of work, not a stateful object.

```php
interface CLICommand {
    public static function get_name(): string;
    public static function get_description(): string;
    public static function run( array $args, array $assoc_args ): void;
}
```

Note this interface is purely a contract: nothing in the framework calls
`WP_CLI::add_command()` for you. A consumer registers the command itself (usually
from a `Registrable`'s `register_hooks()` behind a `WP_CLI` check), using these
methods to supply the name, description, and callback. It standardises the
*shape* of a command across skeletons, not its registration.

The command itself is a plain static class:

```php
use rtCamp\WPPrimitives\Contracts\Interfaces\CLICommand;

final class ReindexCommand implements CLICommand {
    public static function get_name(): string {
        return 'acme reindex';
    }

    public static function get_description(): string {
        return 'Rebuild the Acme search index.';
    }

    /**
     * @param string[]              $args       Positional arguments.
     * @param array<string, string> $assoc_args Flags.
     */
    public static function run( array $args, array $assoc_args ): void {
        \WP_CLI::log( 'Reindexing…' );
        \WP_CLI::success( 'Done.' );
    }
}
```

Registration is the consumer's, and `ConditionallyRegistrable` is the natural
place for the `WP_CLI` check — the loader then skips the class entirely outside
WP-CLI instead of the class guarding itself:

```php
use rtCamp\WPPrimitives\Contracts\Interfaces\ConditionallyRegistrable;

final class CliCommands implements ConditionallyRegistrable {
    /** @var array<int, class-string<CLICommand>> */
    private const COMMANDS = [ ReindexCommand::class ];

    public function can_register(): bool {
        return defined( 'WP_CLI' ) && \WP_CLI;
    }

    public function register_hooks(): void {
        foreach ( self::COMMANDS as $command ) {
            \WP_CLI::add_command(
                $command::get_name(),
                [ $command, 'run' ],
                [ 'shortdesc' => $command::get_description() ]
            );
        }
    }
}
```

`CliCommands` goes in a module's `get_classes()` like any other service. Because
`WP_CLI::add_command()` is available as soon as WP-CLI has bootstrapped, no
further hook is needed — registering directly from `register_hooks()` is correct
here, unlike the `Abstract*` classes that must wait for `init`.

## Traits

### `Loader`

[`inc/Contracts/Traits/Loader.php`](../inc/Contracts/Traits/Loader.php)

The engine described in [architecture.md](architecture.md). A class `use`s it to
gain the ability to load other classes.

| Member | Visibility | Purpose |
|---|---|---|
| `load( array $classes ): void` | `protected` | Instantiate each unique class; register hooks if `Registrable` (respecting `ConditionallyRegistrable`); cache if `Shareable`. Creates a fresh `Container` each call. |
| `get_shared( string $id ): object` | `public` | Return an instance previously cached as `Shareable`. Throws `RuntimeException` if `load()` hasn't run, or if `$id` was never cached as a `Shareable` in that load (not `Shareable`, or not among the loaded classes). |
| `$container` | `private Container` | The per-load instance store. Not accessible to consumers — go through `get_shared()`. |

Because `load()` is `protected`, only the class that `use`s the trait can start a
load — you can't load from outside. That's why the entry point is a consumer's
own `Main` class and each `AbstractModule`, both of which `use Loader`.

Duplicate names in one class list are loaded once, preventing duplicate hook
registration. A subsequent call to `load()` creates a new container rather than
extending the old one, so instances shared by a previous call are no longer
retrievable from that loader.

### `Singleton`

[`inc/Contracts/Traits/Singleton.php`](../inc/Contracts/Traits/Singleton.php)

Standard lazy singleton with the usual guards.

```php
trait Singleton {
    protected static $instance;          // ?static
    protected function __construct() {}  // override in the using class
    public static function get_instance(): static;
    final public function __clone();     // _doing_it_wrong, blocks cloning
    final public function __wakeup();     // _doing_it_wrong, blocks unserialize
}
```

Points that matter:

- It uses **late static binding** (`static::$instance`, `new static()`), allowing
  the using class to override or initialize the protected storage. Unrelated
  classes that each use the trait have separate properties, but a class and its
  subclasses share one storage slot. Do not call `get_instance()` on a subclass
  of a singleton: whichever side is resolved first occupies the slot for both.
- The constructor is `protected` and empty; the using class overrides it to do
  setup. Direct `new` is blocked.
- `get_instance()` stores the instance after the constructor returns. If the
  constructor performs work that can re-enter `get_instance()`—for example a
  `Main` constructor that loads classes whose constructors reach back to
  `Main`—assign `static::$instance = $this` as the constructor's first statement.
  Otherwise the re-entrant call starts another construction.
- `__clone()` and `__wakeup()` are `final` and emit `_doing_it_wrong()` — the
  instance can't be duplicated or revived through deserialization.
- The trait's docblock states up front that singletons are an anti-pattern;
  prefer dependency injection (or `Shareable` + `get_shared()`) and keep this for
  genuine process globals.

## `Container`

[`inc/Container.php`](../inc/Container.php) — strictly it lives at the `inc/` root
rather than under `Contracts/`, but it's the storage half of the `Loader`, so
it's documented here.

A deliberately tiny instance map, keyed by class name.

```php
final class Container {
    public function set( string $id, object $instance ): void;
    public function get( string $id ): object;   // throws RuntimeException if absent
    public function has( string $id ): bool;
}
```

It is **not** a DI container in the PSR-11 sense — it does no auto-wiring, no
factories, no resolution. It just remembers objects the `Loader` was told to
keep. `get()` on a missing key throws a `RuntimeException` with the class name in
the message. It's `final`, so consumers compose it rather than extend it.

---

See [abstracts.md](abstracts.md) for the base classes that implement
`Registrable`, and [architecture.md](architecture.md) for how these pieces move
together at boot.
