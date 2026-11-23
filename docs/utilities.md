---
sidebar_position: 7
sidebar_label: Utilities
---

# Utilities & services

Standalone helpers under `inc/Utils/` (plus the `Container`, documented with the contracts). None of them participate in the registration flow — they're well-tested building blocks a service can hold, inject, or register as `Shareable`. Most are **instance-based and context-scoped**, the same pattern as the loaders: construct with the package's slug/key, and two consumers never collide.

| Utility                                                       | One-liner                                                                     |
| ------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| [`Encryptor`](#encryptor)                                     | Authenticated AES-256-GCM encryption for values stored in the DB              |
| [`Cache`](#cache)                                             | Typed wrapper over the WP object cache, group-namespaced, with optional SWR   |
| [`Logger`](#logger)                                           | PSR-3-style logger that writes to `error_log()` only when `WP_DEBUG` is on    |
| [`Transients`](#transients)                                   | Prefix-namespaced wrapper over the WP transient API; multi-instance by design |
| [`FeatureSelector`](#featureselector)                         | Fail-closed feature-flag registry with per-context toggles                    |
| [`FeatureSelectorSettingsPage`](#featureselectorsettingspage) | Admin page that renders a `FeatureSelector`'s flags as checkboxes             |
| [`Timer`](#timer)                                             | Named start/stop/lap timers in float seconds, shared across hooks/scopes      |
| [`Container`](contracts.md#container)                         | Tiny instance map (the storage half of the `Loader`)                          |

## Encryptor

[`inc/Utils/Encryptor.php`](../inc/Utils/Encryptor.php) — authenticated encryption for sensitive values before they go into the database (API tokens, secrets). **AES-256-GCM** by default.

The OpenSSL PHP extension must be loaded when `encrypt()` or `decrypt()` is called. If it is unavailable, the method reports incorrect usage and returns `false`.

GCM is _authenticated_: it produces an auth tag that makes tampering detectable, so a modified ciphertext fails to decrypt instead of silently returning garbage. The class hard-rejects any cipher whose name doesn't end in `-gcm` (the stored `IV ‖ tag ‖ ciphertext` layout is GCM-specific) — constructing it with, say, `aes-256-cbc` throws an `InvalidArgumentException`.

```php
$enc   = new Encryptor( $key );      // key supplied at construction
$blob  = $enc->encrypt( $secret );   // base64 string, or false on failure
$plain = $enc->decrypt( $blob );     // original string, or false on tamper/failure
```

- A fresh random IV per call means encrypting the same value twice yields different blobs — correct, but you can't compare ciphertexts for equality.
- `decrypt()` returns **`false`** on any failure (failed auth = tampering, or non-base64 input). Always check for `false`; it is not an exception.
- Override the protected `key()` seam to source the key from a KMS / env / rotated secret without touching the crypto. It must never return an empty string — the base throws if no key is available, on purpose.
- Key generation, storage, and rotation are the consumer's job; the class only encrypts and decrypts.

## Cache

[`inc/Utils/Cache.php`](../inc/Utils/Cache.php) — a typed wrapper over WordPress's object-cache functions, with **per-consumer group namespacing** and an optional **stale-while-revalidate (SWR)** path for stampede protection.

Instance-based, configured with a context slug so each consumer's cache groups are namespaced and can't collide with another plugin/theme using the same group name through a shared object cache:

```php
$cache = new Cache( 'my-plugin' );
$nav   = $cache->remember( 'nav_items', fn() => build_nav(), 'theme', 300 );
```

`remember()` returns the cached value or computes, stores, and returns it. Like the other services, register a `Cache` instance as `Shareable` in a consumer's container, or extend it to change the backend behaviour.

The direct wrapper methods are also available:

| Method                                                          | Behavior                                                                                                    |
| --------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| `get( $key, $group = '', $force = false, &$found = null )`      | Read a value; `$found` distinguishes a miss from a stored falsy value.                                      |
| `set( $key, $value, $group = '', $expiration = 0 )`             | Store a serializable value; `0` means no expiry.                                                            |
| `delete( $key, $group = '' )`                                   | Delete one key. It does not delete SWR companion keys.                                                      |
| `flush_group( $group )`                                         | Flush the namespaced group when the active cache backend supports group flushing; otherwise return `false`. |
| `remember( $key, $callback, $group = '', $expiration = 0 )`     | Return a hit or synchronously generate and store a miss.                                                    |
| `remember_swr( $key, $callback, $group = '', $expiration = 0 )` | Add stale data and locking around an expensive regeneration.                                                |

With context `my-plugin`, group `posts` becomes `my-plugin:posts`, and the empty group becomes `my-plugin`. Override `resolve_group()` to change that scheme.

For hot keys where a simultaneous miss would stampede the backend, use `remember_swr( $key, $callback, $group, $expiration )`. On expiry one caller takes a short lock and regenerates the value in the foreground while every other caller is served the still-usable **stale** copy — so only one regeneration runs at a time:

```php
$feed = $cache->remember_swr( 'home_feed', fn() => build_feed(), 'theme', 300 );
```

It keeps two companion entries — `{key}_stale` (the fallback, stored at roughly 2× the TTL) and `{key}_lock` — so avoid passing a `$key` that already ends in `_stale` or `_lock`.

On a stale hit, all callers—including the caller that wins the lock and performs the synchronous regeneration—receive the stale value for that request. The new value is stored for subsequent requests. On a cold start with neither fresh nor stale data, the lock winner receives the generated value.

Cross-process stampede protection requires a persistent object-cache backend such as Redis or Memcached. With WordPress's default request-local cache, the API still behaves as a get-or-set helper, but locks are not shared across PHP workers. For complete invalidation of an SWR entry, flush its group; deleting the primary key leaves its `_stale` and `_lock` companions intact.

## Logger

[`inc/Utils/Logger.php`](../inc/Utils/Logger.php) — a PSR-3-_style_ logger that writes to `error_log()`, with a consumer prefix on every line so a shared log stream stays attributable.

Instance-based and context-scoped like the others — construct one per consumer with that package's prefix; a theme and a plugin sharing the process each own an independent logger instead of routing through one global instance:

```php
$log = new Logger( 'my-plugin' );
$log->info( 'Cache warmed', [ 'items' => 42 ] );
// error_log: [INFO] [my-plugin] Cache warmed {"items":42}
```

- **Silent in production:** nothing is written unless logging is enabled, which by default tracks `WP_DEBUG`. Safe to leave calls in shipped code — they no-op where `WP_DEBUG` is off.
- **PSR-3-style, not the full interface:** ships the four levels rtCamp plugins use — `debug()`, `info()`, `warning()`, `error()` — plus the generic `log()`, and stops there. No `psr/log` dependency (zero runtime deps), but the method names match PSR-3 so a later swap to Monolog needs no caller changes.
- Override the protected `is_enabled()` seam to gate on something other than `WP_DEBUG` (an env var, a feature flag) or to force logging on for a specific subsystem — without touching the formatting. **Not `final`**, and internal calls go through `$this` so the override takes effect.

## Transients

[`inc/Utils/Transients.php`](../inc/Utils/Transients.php) — a thin wrapper over WordPress's transient API (`get_transient` / `set_transient` / `delete_transient`) that **prefixes every key with a per-instance namespace**. Two modules that both reach for `set_transient( 'user_count', … )` would otherwise overwrite each other; prefix injection makes that collision impossible.

It's the one utility here that is **multi-instance out of necessity** — there is no shared/singleton form, because isolated key namespaces are the whole point. Each consumer constructs its own wrapper:

```php
$store = new Transients( 'my-module' );
$store->set( 'user_count', 42, HOUR_IN_SECONDS );
$store->get( 'user_count' );   // 42 — never sees another module's 'user_count'
$store->delete( 'user_count' );
```

- The prefix is namespaced as `<strlen(prefix)>:<prefix>_<key>`, so the prefix/key boundary stays unambiguous even with underscores in either part — `( 'mod', 'a_b' )` and `( 'mod_a', 'b' )` resolve to **different** transients, not the same `mod_a_b`.
- Regular single-site transients only. A multisite / site-transient variant can come later by overriding the protected `resolve_key()` seam (the same extension pattern as `Cache`'s `resolve_group()`) — **not `final`**.
- `set()` defaults to a one-day TTL; pass `0` for no expiry.

## FeatureSelector

[`inc/Utils/FeatureSelector.php`](../inc/Utils/FeatureSelector.php) — a feature-flag registry with per-context toggle storage. It is **fail-closed**: only a key produced by a registered flag can resolve, and an unknown normalized key returns `false` from `is_enabled()`.

```php
$features = new FeatureSelector( 'my-plugin' );
$features->register( [ 'dark-mode' => [ 'name' => 'Dark Mode' ] ] );

if ( $features->is_enabled( 'dark-mode' ) ) { /* … */ }
```

For a registered flag the lookup precedence is:

1. **PHP constant** — an instant override (tests, or an emergency disable via `wp-config.php`);
2. **stored toggle** — the flag's entry in the per-context feature option (e.g. written by a settings page);
3. **default `true`** — features ship on. The selector exists to turn things _off_, not on.

### Registry and state API

| Method                                 | Behavior                                                                                |
| -------------------------------------- | --------------------------------------------------------------------------------------- |
| `register( $features )`                | Accept one slug, a list of slugs, or a `slug => metadata` map. First registration wins. |
| `is_enabled( $flag )`                  | Resolve a registered flag through constant, stored value, then default.                 |
| `enable( $flag )` / `disable( $flag )` | Persist a registered flag's state; return `false` and warn for an unknown flag.         |
| `get_registered()`                     | Return registered slugs in registration order.                                          |
| `get_features()`                       | Return metadata keyed by original slug.                                                 |
| `get_context()`                        | Return the constructor context without normalization.                                   |
| `shared_option_key()`                  | Return the single option holding this context's flags.                                  |
| `flag_key( $flag )`                    | Return the normalized, dash-preserving storage key.                                     |
| `constant_name( $flag )`               | Return the PHP constant used to lock the flag.                                          |

For context `my-plugin` and flag `dark-mode`, the defaults are option `my_plugin_features`, array key `dark-mode`, and constant `MY_PLUGIN_FEATURE_DARK_MODE`. Registration detects collisions after key normalization and keeps the first registration. Because lookups use that same normalized key, spelling variants that normalize identically refer to the same registered flag; callers should nevertheless use the original registered slug.

A defined constant always wins over the stored toggle. The string `'false'` is treated as false to handle a common `wp-config.php` mistake; other values use normal PHP boolean conversion.

## FeatureSelectorSettingsPage

[`inc/Utils/FeatureSelectorSettingsPage.php`](../inc/Utils/FeatureSelectorSettingsPage.php) — an admin settings page that lists every flag registered with an injected `FeatureSelector` as a checkbox. It extends [`AbstractSettingsPage`](abstracts.md#abstractsettingspage) and uses the WordPress Settings API end-to-end (the form posts to `options.php`; no custom handler). The page lives under **Settings → {Context} Features**, with the slug, option group, and titles all derived from the selector's context.

```php
$features = new FeatureSelector( 'my-plugin' );
$features->register( [ 'dark-mode' => [ 'name' => 'Dark Mode' ] ] );

// FeatureSelectorSettingsPage is abstract — subclass it and return the shared
// registry from get_selector().
final class MyFeaturesPage extends FeatureSelectorSettingsPage {
    protected function get_selector(): FeatureSelector {
        return MyPlugin::features(); // the shared FeatureSelector instance
    }
}

( new MyFeaturesPage() )->register_hooks();
```

It's the ready-made UI for the toggles `FeatureSelector` reads — register it like any other `Registrable`.

The page registers a single array option with a sanitization callback. A flag locked by a PHP constant renders as disabled, and saving the page preserves its previous stored value. `register_fields()` runs only on `admin_init`; the setting itself is also registered on `rest_api_init`, where the wp-admin field helpers are unavailable. The render method performs its own capability check.

## Timer

[`inc/Utils/Timer.php`](../inc/Utils/Timer.php) — named timing segments that persist across scopes within a single request: `start()` a timer in one hook or file and `stop()` it in another, with no globals or hand-passed `microtime( true )` values. `lap()` records intermediate splits; `get()` / `get_all()` expose the collected data (in float seconds, like `$wpdb->queries`).

```php
$timer = new Timer();
$timer->start( 'render' );
$timer->lap( 'render', 'after_query' );
// … later, in another hook holding the same instance …
$elapsed = $timer->stop( 'render' );   // float seconds
$all     = $timer->get_all();          // every timer, with computed elapsed
```

`get( $label )` returns `null` for an empty or unknown label. Otherwise it returns:

```php
[
    'start'   => 0.0,       // absolute microtime value
    'end'     => null,      // absolute microtime value, or null while running
    'elapsed' => 0.0,       // seconds since start
    'laps'    => [ 'after_query' => 0.0 ], // seconds since start, keyed by lap name
]
```

`get_all()` returns that shape keyed by timer label. All running timers in one `get_all()` call use the same time snapshot.

- **Instance-based, not a singleton.** The start-here / stop-there pattern shares state by sharing the _instance_: register one as `Shareable` in the consumer's container (the same pattern as `Cache`) so every hook resolves the same object, while a theme and a plugin keep their own decoupled timer sets.
- **Misuse is loud, reads are silent.** Empty / duplicate / never-started / already-stopped labels are reported via `_doing_it_wrong()` (the WordPress convention for developer error), not exceptions. `get()` / `get_all()` never emit notices — an empty or unknown label just returns `null`.
- A running timer's `elapsed` is measured at the moment you read it, without stopping it.

## Container

`Container` lives at the `inc/` root and is the storage half of the `Loader` — a deliberately tiny `set` / `get` / `has` instance map, not a PSR-11 auto-wiring container. Documented with the registration pieces in [contracts.md](contracts.md#container).

---

Back to [index.md](index.md).
