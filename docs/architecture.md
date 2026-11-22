---
sidebar_position: 3
sidebar_label: Architecture
---

# Architecture — how a class becomes a live hook

The framework's job is narrow: take a list of class names and turn them into
running WordPress code, predictably, without each class having to know how it was
wired. Everything else (the abstracts, the loaders) is built on top of this one
flow. Understand it once and the rest of the codebase reads quickly.

## The three pieces

```
Registrable   — "I have hooks to register."        (interface)
Loader        — "Give me classes, I'll register and cache them." (trait)
Container     — "I hold instances you asked to keep." (class)
```

A class opts into the system by implementing an interface; the `Loader` reacts to
the interfaces it finds. There is no central registry, no reflection, no
auto-wiring — the `Loader` literally just loops and does `instanceof` checks. That
bluntness is deliberate: it makes the load order obvious and the whole thing
debuggable by reading `Loader::load()` top to bottom.

## The load loop

[`Loader::load()`](../inc/Contracts/Traits/Loader.php) is the heart of the
framework. Given `class-string[]`, for each class it:

1. **Skips a duplicate** when the same class name already appeared in this load.
2. **Instantiates** it with `new $class_name()` — every loadable class must be
   constructible with no arguments (i.e. no *required* constructor parameters;
   all-optional is fine).
3. **Registers hooks** if it is `Registrable` — but first, if it is also
   `ConditionallyRegistrable`, it calls `can_register()` and skips registration
   when that returns `false`.
4. **Caches the instance** if it is `Shareable`, storing it in a `Container`
   keyed by class name.

```php
foreach ( $classes as $class_name ) {
    if ( isset( $seen[ $class_name ] ) ) {
        continue;
    }
    $seen[ $class_name ] = true;

    $instance = new $class_name();

    if ( $instance instanceof Registrable ) {
        if ( ! $instance instanceof ConditionallyRegistrable || $instance->can_register() ) {
            $instance->register_hooks();
        }
    }

    if ( $instance instanceof Shareable ) {
        $this->container->set( $class_name, $instance );
    }
}
```

The two checks are **independent**. A class can be `Registrable` and `Shareable`
at once — its hooks get registered *and* its instance gets kept. A class that is
neither is just instantiated and dropped (occasionally that's all you want — the
constructor did the work).

`load()` creates a **fresh `Container` each call** and assigns it to
`$this->container`. So the shared instances belong to the loader that loaded
them, not to a global. Call `get_shared( $class_name )` on that same loader to
retrieve one; calling it before `load()` — or for a class that wasn't
`Shareable` — throws a `RuntimeException`.

Because another `load()` replaces the container, use one complete class list per
loader. A second call does not add to the previously shared set.

## Modules: loaders that hold loaders

Most skeletons don't hand the top-level loader a flat list of services. They hand
it a list of **modules**, and each module loads its own services. That's the only
reason [`AbstractModule`](../inc/Contracts/Abstracts/AbstractModule.php) exists:

```php
abstract class AbstractModule implements Registrable {
    use Loader;

    abstract protected function get_classes(): array;

    public function register_hooks(): void {
        $this->load( $this->get_classes() );
    }
}
```

A `Module` *is* `Registrable`, and its `register_hooks()` simply loads its
children. So the structure is recursive:

```
Main (uses Loader)
  └─ load([ ContentModule::class, AdminModule::class, ... ])
        ContentModule (AbstractModule → uses Loader, is Registrable)
          └─ register_hooks() → load([ ArticlePostType::class, GenreTaxonomy::class ])
                ArticlePostType (AbstractPostType → is Registrable)
                  └─ register_hooks() → add_action( 'init', [ $this, 'register' ] )
```

The top `Main` calls `register_hooks()` on the module while looping, which calls
`load()` on the module's children, which calls `register_hooks()` on each leaf
service. One uniform mechanism the whole way down. A module that wants its
children retrievable later can have them implement `Shareable` and expose them
via the module's own `get_shared()`.

> **Where is `Main`?** Not in this package. The framework gives the skeleton the
> `Loader` trait and the contracts; the skeleton writes its own `Main`/`Plugin`
> entry class that `use`s `Loader` and kicks off the first `load()` on `plugins_loaded`
> (or theme setup). The framework is the spine, not the application.

## When hooks actually fire

`load()` runs synchronously the moment it's called, so **`register_hooks()` runs
immediately** — but the hooks it registers fire later, on WordPress's schedule.
This is the important mental split:

- The framework's work (instantiating, registering hooks, caching) happens at
  **load time** — typically early, on `plugins_loaded` or theme setup.
- The actual WordPress registration (`register_post_type`, `add_shortcode`, REST
  routes, menus) happens when the **abstract's own hook fires** — `init`,
  `rest_api_init`, `admin_menu`, and so on.

So the abstracts don't register their thing during `load()`; they register a
*hook* during load, and the thing registers when that hook runs. This is why you
can safely `load()` everything up front without worrying about whether `init` has
happened yet. Each abstract documents which hook it uses — see
[abstracts.md](abstracts.md).

## Sharing vs. singletons

The framework offers two ways for one object to be reachable from elsewhere, and
they are not the same thing:

- **`Shareable` + `get_shared()`** — the preferred one. The instance is owned by
  a loader and handed out on request. It's still a normal object; the loader just
  kept a reference. Both [`Shareable`](../inc/Contracts/Interfaces/Shareable.php)
  and the [`Singleton`](../inc/Contracts/Traits/Singleton.php) trait warn against
  overuse in their own docblocks, but grade it differently: `Shareable` calls
  itself a *soft* anti-pattern (hidden shared state), while `Singleton` calls
  singletons an anti-pattern outright and says to prefer dependency injection.
  Use either only when a hooked object genuinely must be retrieved later, not as a
  default.
- **`Singleton` trait** — global `ClassName::get_instance()` access with cloning
  and deserialization guarded. Use it only when something truly must be a process
  global and you can't thread it through a loader. A class and its subclasses
  share the trait's single storage slot, so do not resolve a child of a singleton
  through `get_instance()`.

If you can pass the object in a constructor instead, do that. The
`ComponentLoader`/`TemplateLoader` pattern (a `Shareable` subclass fetched via
`get_shared()`) is the framework's own example of the middle path.

## What this buys a consumer

- **A class declares its nature, not its plumbing.** "I'm `Registrable`" / "I'm
  `Shareable`" is the entire contract. The loader does the rest.
- **Uniform, readable startup.** Every service starts the same way; there's one
  place (`Loader::load()`) that explains the whole boot.
- **Conditional features for free.** Implement `ConditionallyRegistrable` and a
  feature behind a flag, a CLI-only command, or an admin-only service simply
  doesn't register when `can_register()` is false — no scattered `if` guards
  inside `register_hooks()`.

Next: [contracts.md](contracts.md) for the exact interface/trait signatures, or
[abstracts.md](abstracts.md) for the base classes that ride on top of this flow.
