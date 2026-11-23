---
sidebar_position: 6
sidebar_label: Loaders
---

# Loaders — assets, components & templates

Three concrete classes handle the "files on disk → output in the page" side of a skeleton:

- **`AssetLoader`** — register/enqueue scripts, styles, script modules, and block manifests, reading build metadata from `*.asset.php` files.
- **`ComponentLoader`** — resolve and render self-contained PHP component partials, auto-enqueuing their CSS/JS.
- **`TemplateLoader`** — resolve and render WordPress template parts with theme overrides.

`ComponentLoader` and `TemplateLoader` share one idea worth learning once: the **theme-override hierarchy**. Read that section first; it explains a behaviour both of them depend on.

Unlike the contracts, these are **concrete classes you extend or instantiate**. None of them is `Registrable` on its own — a consumer typically wraps one in a `Shareable` subclass and fetches it via `get_shared()`, so the same instance is reused across the request.

---

## The theme-override hierarchy

WordPress lets a child theme override a parent theme override a plugin. Both `ComponentLoader` and `TemplateLoader` reproduce that precedence, but with one refinement: **they only add the layers that sit _above_ the package that owns the loader.** A file is resolved by walking, most-specific first:

```
child theme   →   parent theme   →   the package itself
```

…but layers the package already lives in are skipped, because they can't "override" the package — they _are_ it:

| The owning package is…           | Layers searched           |
| -------------------------------- | ------------------------- |
| a **plugin** (outside any theme) | child? → parent → package |
| the **parent theme**             | child? → self             |
| the **child theme**              | self only                 |

(`child?` appears only when a distinct child theme is actually active.)

This keeps the search list minimal and correct without the consumer configuring anything: a plugin gets all three layers; a theme's own loader collapses the redundant layer automatically. `ComponentLoader` figures this out by comparing its base directory against `get_stylesheet_directory()` / `get_template_directory()`; `TemplateLoader` does the same with `str_starts_with()` on its template directory. `ComponentLoader` memoises the resolved loader hierarchy for the request; `TemplateLoader` recomputes its search paths each call but memoises each located template path.

---

## AssetLoader

[`inc/AssetLoader.php`](../inc/AssetLoader.php) — a concrete, injectable loader for built front-end assets. It's a plain class (not a trait) specifically so it has an **instance identity**: a consumer either extends it or holds one and passes it around. It's the building block the other two loaders resolve assets through.

### Construction

```php
new AssetLoader( $base_dir, $base_url, $assets_dir );
```

- `$base_dir` — filesystem root of the package (plugin/theme), trailing-slashed internally.
- `$base_url` — matching URL root.
- `$assets_dir` — built-assets directory **relative** to the base (no surrounding slashes), e.g. `build`.

An asset is then addressed by a path **relative to `$assets_dir`, without the extension** — e.g. `register_script( 'my-app', 'app' )` looks for `<base_dir>/<assets_dir>/app.js`.

`get_base_dir()` returns the normalized, trailing-slashed package directory and `get_assets_dir()` returns the relative assets directory. These are useful when a subclass needs to resolve another build artifact. `handle( $name )` prefixes a short handle with `static::HANDLE_PREFIX`; override that constant in a consumer subclass to namespace all handles consistently.

### Registration methods

| Method                                                                              | Registers                                                                                                                                                                     | Returns                                                                                                                                                          |
| ----------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `register_script( $handle, $filename, $deps = [], $ver = null, $in_footer = true )` | a script                                                                                                                                                                      | `bool` from `wp_register_script()`, or `false` if the file is missing                                                                                            |
| `register_style( $handle, $filename, $deps = [], $ver = null, $media = 'all' )`     | a stylesheet                                                                                                                                                                  | `bool` from `wp_register_style()`, or `false` if missing                                                                                                         |
| `register_script_module( $handle, $filename, $deps = [], $ver = null )`             | an ES module                                                                                                                                                                  | `false` if the file is missing, else `true` (core's `wp_register_script_module()` returns void, so success past the file check can't be reported more precisely) |
| `register_block_manifest( $block_path, $manifest_file )`                            | all blocks in a manifest via `wp_register_block_types_from_metadata_collection()` (WordPress 6.8+; falls back to registering each block from its own `block.json` on 6.5–6.7) | `void`                                                                                                                                                           |
| `has_asset( $filename, $extension )`                                                | —                                                                                                                                                                             | `bool` — does the file exist under base+assets                                                                                                                   |

These **register**; they don't enqueue. Call `wp_enqueue_script()` / `wp_enqueue_style()` with the handle afterwards (the `ComponentLoader` does this for you for component assets).

Asset filenames may contain relative subdirectories but may not contain `..`. The extension must be a bare suffix without a leading dot or path separator. Unsafe paths are rejected before the filesystem is probed.

### The `*.asset.php` manifest

This is the convenience that makes `AssetLoader` worth using. The `@wordpress/scripts` build emits a sidecar `app.asset.php` next to `app.js`, returning `[ 'dependencies' => [...], 'version' => '<hash>' ]`. For each registration the loader:

1. **requires the asset file to exist** — if `app.js` is missing it emits `_doing_it_wrong()` and returns `false` (nothing to register).
2. reads the **optional** `app.asset.php` for dependencies and version. An invalid manifest is tolerated with a warning; a missing one is fine.
3. falls back to the asset's **`filemtime()`** as the version when the manifest doesn't supply one — so cache-busting works even without a build manifest.

Explicit `$deps` / `$ver` arguments always win over the manifest. Pass `$deps` empty and `$ver` null to inherit from the manifest, which is the common case.

```php
$assets = new AssetLoader( MY_PLUGIN_PATH, MY_PLUGIN_URL, 'build' );
add_action( 'wp_enqueue_scripts', function () use ( $assets ) {
    $assets->register_script( 'my-app', 'app' );   // deps + version from build/app.asset.php
    wp_enqueue_script( 'my-app' );
} );
```

---

## ComponentLoader

[`inc/ComponentLoader.php`](../inc/ComponentLoader.php) — renders **components**: a component is a self-contained, render-only package of PHP plus built CSS/JS. `render( 'Button', [ … ] )` resolves `Button/Button.php` across the hierarchy, includes it in an isolated scope, and (by default) registers + enqueues its matching stylesheet and script.

### Layout it expects

| Thing            | Path                                      |
| ---------------- | ----------------------------------------- |
| Component PHP    | `<root>/src/components/{Name}/{Name}.php` |
| Component style  | `<assets dir>/css/components/{Name}.css`  |
| Component script | `<assets dir>/js/components/{Name}.js`    |

(`src/components`, `css/components`, `js/components` are the defaults — override the `$php_dir` / `$style_dir` / `$script_dir` properties to change them.)

### Wiring it

A consumer subclasses it to set its **context** (used to namespace asset handles and target the filters) and to supply an `AssetLoader`:

```php
final class Components extends ComponentLoader implements Shareable {
    public function __construct() {
        parent::__construct( new AssetLoader( MY_PLUGIN_PATH, MY_PLUGIN_URL, 'build' ) );
    }
    protected function get_context(): string { return 'my-plugin'; }
}
```

The `AssetLoader` can be injected via the constructor _or_ supplied by overriding `get_asset_loader()` (useful when the subclass is loaded by the framework `Loader` with no constructor args and resolves a shared instance lazily). If neither is provided, the first render throws a `RuntimeException` telling you so.

### Rendering

```php
$components->render( 'Button', [ 'label' => 'Go' ] );          // echo
$html = $components->get( 'Button', [ 'label' => 'Go' ] );     // capture to string
```

The third `$options` argument controls behaviour per call:

- `script` / `style` (default `true`) — whether to auto-enqueue that asset.
- `allow_override` (default `true`) — when `false`, the component resolves from the package's **own** directory only, ignoring theme overrides.

Inside the component file, `$args`, `$name`, and the resolved `$options` are in scope — and **`$this` is not**: the file is required from a `private static` method precisely so a component can't reach back into the loader. It can forward `$options` to nested `render()` calls, which is how component composition works.

### Behaviours worth knowing

- **Name validation is a security boundary.** A component name must match `^[A-Za-z0-9_-]+$` and be ≤128 chars; anything with a slash, a `..`, or other path characters is rejected outright, so a name can never escape the components directory. Treat that regex as load-bearing.
- **Asset resolution follows the same hierarchy as the PHP.** A child theme can ship `css/components/Button.css` and override just the style while reusing the plugin's PHP.
- **Three extension points, namespaced by context.** Each hook name is prefixed with the loader's context slug — `{context}/component_before_render` / `…_after_render` actions, the `{context}/component_should_enqueue` filter (return `false` to suppress auto-enqueue), and the `{context}/component_asset_handle` filter — so one package's handler never fires for another's (the context slug is also passed as an argument). This mirrors `TemplateLoader`'s prefixed hooks.
- **Per-request caching.** Resolved component metadata and the asset-loader hierarchy are memoised; both depend on the active theme, so call `clear_cache()` after a `switch_theme()`/`switch_to_blog()` in a long-lived (Shareable) loader. `TemplateLoader` folds the resolved paths into its lookup key automatically.
- A missing component emits `_doing_it_wrong()` and renders nothing rather than throwing a fatal error.

### Component hooks

With a context of `my-plugin`, the hooks use the following names and arguments:

| Hook                                 | Type   | Arguments                                |
| ------------------------------------ | ------ | ---------------------------------------- |
| `my-plugin/component_before_render`  | action | `$name, $args, $context`                 |
| `my-plugin/component_after_render`   | action | `$name, $args, $context`                 |
| `my-plugin/component_should_enqueue` | filter | `$enqueue, $name, $asset_type, $context` |
| `my-plugin/component_asset_handle`   | filter | `$handle, $name, $asset_type, $context`  |

`$asset_type` is `style` or `script`. The should-enqueue filter must return a boolean; the handle filter must return a string. Override `get_enqueue_settings()` to change the default `script` and `style` values for all renders from a loader.

---

## TemplateLoader

[`inc/TemplateLoader.php`](../inc/TemplateLoader.php) — resolves **template parts** with theme overrides. The key difference from `ComponentLoader`: a template part is loaded through WordPress's own `load_template()`, so the global `$post`, `$wp_query`, etc. are in scope — it's a _template_, not an isolated component.

### Wiring it

Like `ComponentLoader`, you extend it and share it:

```php
// Plugin: own templates in the plugin, theme overrides at my-theme/my-plugin/.
final class Templates extends TemplateLoader implements Shareable {
    public function __construct() {
        parent::__construct( 'my_plugin', MY_PLUGIN_PATH . 'templates', 'my-plugin' );
    }
}
```

Constructor args:

- `$hook_prefix` — prefixes every filter/action this loader fires (usually the package slug, snake_case).
- `$template_dir` — absolute path to the package's own templates.
- `$template_theme_dir` — the directory name a theme overrides into, e.g. `my-plugin` resolves to `my-theme/my-plugin/{slug}.php`.
- `$hook_separator` — between prefix and hook name, default `/`.

### Rendering

```php
$templates->render( 'content', 'card', [ 'title' => 'Hello' ] );      // echo
$html = $templates->get( 'content', 'card', [ 'title' => 'Hello' ] ); // string
$path = $templates->locate( 'content', 'card' );                      // path or false
```

Given `slug` + optional `name`, it searches `{slug}-{name}.php` then `{slug}.php`. Resolution puts **template name in the outer loop**, so a more specific `{slug}-{name}` variant in the _plugin_ still beats a generic `{slug}` in the _theme_ — matching WordPress's own `locate_template()` precedence where the name dominates the location.

### Behaviours worth knowing

- **Candidate paths are sanitised** segment by segment with `sanitize_file_name()`, dropping empties and `..`, so a slug/name can't traverse out of the search roots.
- **Filter hooks at every step**, all prefixed: `{prefix}/get_template_part_{slug}` (action), `{prefix}/template_file_names`, `{prefix}/template_args`, `{prefix}/template_paths`, and `{prefix}/located_template`. The paths filter is keyed by numeric priority (lower = higher precedence) so a consumer can splice a layer in between the defaults.
- **Per-request location cache**, cleared with `clear_cache()`.
- A missing template is a silent no-op (`render()` echoes nothing, `locate()` returns `false`).

### Template hooks

For a prefix of `my_plugin` and the default `/` separator:

| Hook                                 | Type   | Arguments / return value                                             |
| ------------------------------------ | ------ | -------------------------------------------------------------------- |
| `my_plugin/get_template_part_{slug}` | action | `$slug, $name, $args`                                                |
| `my_plugin/template_file_names`      | filter | `array $templates, $slug, $name`; return candidate filenames         |
| `my_plugin/template_args`            | filter | `array $args, $slug, $name`; return arguments passed to the template |
| `my_plugin/template_paths`           | filter | `array $paths`; return paths keyed by numeric priority               |
| `my_plugin/located_template`         | filter | `$located, array $templates`; return a path or `false`               |

The request action and argument filter run only through `render()`/`get()`; `locate()` performs location filters without rendering. Exceptions thrown by a component or template propagate to the caller. The `get()` methods clean up their output buffer before rethrowing.

> **`TemplateLoader` vs. `ComponentLoader` — when to use which.** Use a **component** for a reusable, self-contained UI fragment with its own scoped CSS/JS and no dependence on the main query (a button, a card). Use a **template part** for theme-facing markup that should behave like the rest of the theme — in the loop, overridable by site builders, with the query globals available.

> **History.** `TemplateLoader` replaced an earlier `TemplateLoaderTrait` (commit `99a4e77`). It's a concrete class in the `inc/` root now, not a trait under `Contracts/Traits/`.

---

Next: [utilities.md](utilities.md) for the `Encryptor`, or back to [index.md](index.md).
