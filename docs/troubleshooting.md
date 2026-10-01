---
sidebar_position: 8
sidebar_label: Troubleshooting
---

# Troubleshooting

Symptom → cause for the failures the framework produces. Three shapes, and which
one you get is deliberate:

- **Exceptions** for programming errors that must not be survivable (a missing
  dependency, an unresolvable service).
- **`_doing_it_wrong()` notices** for developer mistakes WordPress convention
  says to report rather than throw on. **These are only visible when `WP_DEBUG`
  is on** — if a call "does nothing" in production, re-run it with `WP_DEBUG`
  enabled before assuming the framework is silent.
- **Silent no-ops** where absence is a legitimate state (a template that isn't
  there, an unregistered feature flag).

## Registration & loading

| Symptom | Cause | Fix |
|---|---|---|
| `RuntimeException: Cannot call get_shared() before load() has been called.` | `get_shared()` called on a loader whose `load()` hasn't run yet — often a service reaching for a shared instance during **construction**, while the loader is still mid-loop. | Retrieve on a hook that fires after the load, not in the constructor. |
| `RuntimeException: Instance "Acme\Foo" is not registered in the container.` | The class isn't `Shareable`, or it wasn't in the list this loader loaded, or a **later `load()` on the same loader replaced the container**. | Implement `Shareable`; call `get_shared()` on the loader that actually loaded the class (a module's services live on the module, not on `Main`); use one complete class list per loader. |
| `ArgumentCountError: Too few arguments to function …::__construct()` | A loaded class has a **required** constructor parameter. The loader always calls `new $class_name()`. | Make every parameter optional, or construct the object yourself and pass it in. |
| A class's hooks never fire | It is `ConditionallyRegistrable` and `can_register()` returned `false` — the instance is still constructed, only `register_hooks()` is skipped. | Check the condition (feature flag, `is_admin()`, `WP_CLI`). |
| Hook body runs twice | The same behavior is registered from two different classes, or one class is loaded by two different loaders. Duplicates **within one** class list are already de-duplicated. | Load the class from exactly one place. |
| `LogicException: Acme\Controller::register_routes() must be overridden.` on a REST request | `AbstractRESTController::register_routes()` was not overridden. The base throws instead of being `abstract`, so this surfaces when `rest_api_init` fires, not at class load. | Implement `register_routes()`. |
| Two singletons return the same object | A class and its subclass both resolved through `get_instance()`; the trait's `static::$instance` is **one storage slot** for both. | Don't call `get_instance()` on a subclass. Give each singleton its own `use Singleton;`, or use `Shareable` + `get_shared()`. See [upgrading.md](upgrading.md#100--101). |
| `Error: Access to undeclared static property …::$instance` | Running framework 1.0.0 with a constructor that assigns `static::$instance = $this`. | Upgrade to 1.0.1+, where that is the supported pattern. |

## Assets, components & templates

| Symptom | Cause | Fix |
|---|---|---|
| `_doing_it_wrong`: *Asset file "app.js" is missing. The asset will not be registered.* | No file at `<base_dir>/<assets_dir>/app.js`. The asset path is relative to the assets dir and **carries no extension** in the call. | Build first; check the `AssetLoader` constructor's `$base_dir` / `$assets_dir`. |
| `_doing_it_wrong`: *Asset manifest "…" is invalid; the file modification time will be used as the version.* | `app.asset.php` exists but doesn't return an array. | Regenerate the build. Harmless otherwise — registration continues with a `filemtime()` version. |
| `_doing_it_wrong`: *Block manifest file is missing. Blocks will not be registered.* | `register_block_manifest()` got a manifest path that doesn't resolve under the base dir. | Pass the path **relative to the base dir**, e.g. `build/blocks-manifest.php`. |
| Script registers but never loads | Registration is not enqueueing. | Call `wp_enqueue_script()` with the handle. `ComponentLoader` does this for component assets; `AssetLoader` never does. |
| Handles collide with another package | `HANDLE_PREFIX` left at its `wp-primitives-` default. | Override the constant in the consumer's `AssetLoader` subclass. |
| `RuntimeException: Acme\Components requires an AssetLoader: inject one via the constructor or override get_asset_loader().` | The `ComponentLoader` subclass was constructed with no asset loader — typical when the framework `Loader` instantiates it with no arguments. | Build one in the subclass constructor, or override `get_asset_loader()` to resolve a shared instance lazily. |
| `_doing_it_wrong`: *Component "Foo" could not be resolved.* | No `Foo/Foo.php` under any layer of the hierarchy, **or the name was rejected**: names must match `^[A-Za-z0-9_-]+$` and be ≤128 characters. A slash or `..` is refused outright — that check is a security boundary, not a convenience. | Fix the path or the name. |
| A theme override isn't picked up | The render passed `allow_override => false`, or the override sits in a layer the loader doesn't search (a plugin-owned loader searches child → parent → package; a theme's own loader collapses the redundant layers). | Drop the option, or place the file in a searched layer. |
| Overrides stop resolving after a theme or blog switch | The hierarchy and component metadata are memoised per request, and both depend on the active theme. | Call `clear_cache()` after `switch_theme()` / `switch_to_blog()` on a long-lived (`Shareable`) loader. |
| `render()` outputs nothing, no notice | A missing **template** is a deliberate silent no-op (`locate()` returns `false`). | Check with `locate( $slug, $name )`. |
| Wrong template wins | Resolution puts the **name** in the outer loop, so a `{slug}-{name}.php` in the package beats a generic `{slug}.php` in the theme — matching core's `locate_template()` precedence. | Override the specific variant, not the generic one. |

## Utilities

| Symptom | Cause | Fix |
|---|---|---|
| `InvalidArgumentException: Encryptor only supports GCM ciphers …` | A non-GCM cipher was passed. The stored `IV ‖ tag ‖ ciphertext` layout is GCM-specific. | Use `aes-256-gcm` (the default) or another `-gcm` cipher. |
| `RuntimeException: No encryption key provided. …` | Constructed with an empty key and `key()` not overridden. | Pass a key, or override the protected `key()` seam. |
| `_doing_it_wrong`: *OpenSSL extension is not loaded.* and `encrypt()`/`decrypt()` return `false` | The OpenSSL PHP extension is missing on the host. | Install/enable it. `Encryptor` is the only part of the package that needs it. |
| `decrypt()` returns `false` | Tampered or truncated ciphertext (GCM authentication failed), non-base64 input, or the wrong key. **`false` is a return value, not an exception** — always check it. | Verify the key domain; treat a failure as untrusted data. |
| Same value encrypts to a different blob every time | Correct: a fresh random IV per call. | Never compare ciphertexts for equality; decrypt and compare plaintext. |
| A feature flag is always off | `is_enabled()` is fail-closed and returns `false` for an unregistered flag **silently** — usually a typo, or `register()` running after the check. | Register before checking; use the exact registered slug. |
| `_doing_it_wrong`: *Feature flag "x" collides with already-registered "y" …* | Two slugs normalize to the same storage key. The first registration is kept. | Rename one. |
| `_doing_it_wrong`: *Feature flag "x" is not registered; enable() ignored.* | `enable()` / `disable()` called for an unregistered flag. | Register it first. |
| A toggle on the settings page won't change | The flag is locked by a PHP constant, which always wins over the stored value. The checkbox renders disabled and the stored value is preserved. | Remove the constant from `wp-config.php`. Note `'false'` (the string) is treated as `false`. |
| SWR still stampedes across workers | `remember_swr()` locking needs a **persistent** object cache (Redis, Memcached). With WordPress's default request-local cache the lock isn't shared between PHP workers. | Deploy a persistent backend; without one the API still works as get-or-set. |
| A deleted cache key comes back stale | `delete()` removes the primary key only — `{key}_stale` and `{key}_lock` survive. | `flush_group()` to invalidate an SWR entry completely. |
| Nothing appears in the log | `Logger` writes only when logging is enabled, which tracks `WP_DEBUG` by default. | Enable `WP_DEBUG`, or override the protected `is_enabled()` seam. |
| Two modules overwrite each other's transients | Unprefixed `set_transient()` calls somewhere. `Transients` exists precisely to namespace them. | Construct one `Transients` per module with its own prefix. |
| `_doing_it_wrong`: *Timer "x" has already been started / was never started / has already been stopped* | Timer misuse. Reads are silent: `get()` returns `null` for an unknown label. | Share the same `Timer` **instance** across scopes (register it `Shareable`); a new instance has no timers. |

## Environment

| Symptom | Cause | Fix |
|---|---|---|
| `Class "rtCamp\WPPrimitives\…" not found` | The consumer's `vendor/autoload.php` was never required, or the package resolved from a stale `vendor/`. | Require the autoloader in the plugin/theme entry point; `composer update rtcamp/wp-primitives`. |
| Composer can't find the package | The package was renamed from `rtcamp/wp-framework` to `rtcamp/wp-primitives` in 2.0.0, or the project installs from GitHub without a `repositories` entry. | Require `rtcamp/wp-primitives:^2.0` (see [upgrading.md](upgrading.md#10x--200-package-rename)); for GitHub installs add the VCS entry from [getting-started.md](getting-started.md#install). |
| A missing abstract method only surfaces at runtime | The subclass doesn't implement everything the abstract declares. | Run PHPStan in the consuming package; it catches contract breaks before a request does. |

Still stuck? The classes are small and heavily commented — `Loader::load()` in
[`inc/Contracts/Traits/Loader.php`](../inc/Contracts/Traits/Loader.php) explains
the whole boot in one screen.
