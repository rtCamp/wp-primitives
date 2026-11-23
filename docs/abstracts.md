---
sidebar_position: 5
sidebar_label: Abstracts
---

# Abstracts — the base-class cookbook

The twelve `Abstract*` classes in [`inc/Contracts/Abstracts/`](../inc/Contracts/Abstracts/) are the part of the framework a service author touches most. Each one wraps a single WordPress registration chore so the subclass writes _what_ it is, not _how_ to register it.

Every abstract here (except `AbstractModule`, which is structural; `AbstractFeature`, which gates another service behind a flag; and `AbstractAbility`, which describes an ability that its paired `AbstractAbilityRegistrar` registers) follows the same shape:

- it `implements Registrable`, so the [`Loader`](architecture.md) drives it;
- its `register_hooks()` attaches **one** WordPress hook;
- it declares a few `abstract` methods for the bits that vary (slug, labels, render), and provides sensible, overridable defaults for everything else.

So the lifecycle is always: the loader calls `register_hooks()` → that adds an action on the right WordPress hook → when that hook fires, the actual registration runs. Read [architecture.md](architecture.md) if that split isn't familiar yet.

## Which hook each one uses

| Abstract                   | Registers on                                                 | You must implement                                                                                   |
| -------------------------- | ------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------- |
| `AbstractModule`           | — (loads children immediately)                               | `get_classes()`                                                                                      |
| `AbstractPostType`         | `init`                                                       | `get_slug()`, `get_singular_label()`, `get_plural_label()`, `get_menu_icon()`                        |
| `AbstractTaxonomy`         | `init`                                                       | `get_slug()`, `get_object_types()`, `get_singular_label()`, `get_plural_label()`                     |
| `AbstractBlock`            | `init`                                                       | `get_name()`, `render()`                                                                             |
| `AbstractShortcode`        | `init`                                                       | `get_tag()`, `render()`                                                                              |
| `AbstractRESTController`   | `rest_api_init`                                              | `register_routes()` (see note)                                                                       |
| `AbstractSettingsPage`     | `admin_menu` + `admin_init` + `rest_api_init`                | `get_slug()`, `get_page_title()`, `get_menu_title()`, `get_settings()`, `render()`                   |
| `AbstractAdminPage`        | `admin_menu`                                                 | `get_slug()`, `get_page_title()`, `get_menu_title()`, `render()`                                     |
| `AbstractUserRole`         | `admin_init`                                                 | `get_slug()`, `get_display_name()`, `get_capabilities()`, `get_version()`                            |
| `AbstractFeature`          | — (gates the subclass's own hooks)                           | `get_slug()`, `get_feature_registry()`, plus the subclass's `register_hooks()`                       |
| `AbstractAbility`          | — (registered by its `AbstractAbilityRegistrar`)             | `name()`, `label()`, `description()`, `category()`, `input_schema()`, `output_schema()`, `execute()` |
| `AbstractAbilityRegistrar` | `wp_abilities_api_categories_init` + `wp_abilities_api_init` | `category_slug()`, `category_description()`, `abilities()`                                           |

---

## AbstractModule

[`AbstractModule.php`](../inc/Contracts/Abstracts/AbstractModule.php) — a `Registrable` that `use`s the `Loader` trait. It groups related services so the top-level loader deals in a handful of modules instead of dozens of leaf classes. Its `register_hooks()` just calls `load( $this->get_classes() )`. Covered in full in [architecture.md](architecture.md#modules-loaders-that-hold-loaders).

```php
final class ContentModule extends AbstractModule {
    protected function get_classes(): array {
        return [ ArticlePostType::class, GenreTaxonomy::class ];
    }
}
```

Because a module is itself a `Loader`, services it loads can be `Shareable` and retrieved later via the module's `get_shared()`.

---

## Content registration

### AbstractPostType

[`AbstractPostType.php`](../inc/Contracts/Abstracts/AbstractPostType.php) — the richest abstract. Declare the identity; the base builds the full `register_post_type()` args and registers on `init`.

**Must implement:** `get_slug()` (static), `get_singular_label()`, `get_plural_label()`, `get_menu_icon()`.

```php
final class ArticlePostType extends AbstractPostType {
    public static function get_slug(): string    { return 'article'; }
    public function get_singular_label(): string { return __( 'Article', 'my-plugin' ); }
    public function get_plural_label(): string   { return __( 'Articles', 'my-plugin' ); }
    public function get_menu_icon(): string      { return 'dashicons-media-document'; }
}
```

That's a complete, REST-enabled, archive-having post type. To go further, override the seam you need rather than the whole thing:

| Override                     | Default                                                | Use it to                                                                                      |
| ---------------------------- | ------------------------------------------------------ | ---------------------------------------------------------------------------------------------- |
| `get_editor_supports()`      | `title, editor, author, thumbnail, excerpt, revisions` | add/remove editor features (e.g. `custom-fields`).                                             |
| `get_supported_taxonomies()` | `[]`                                                   | associate taxonomies (registered against this type via `register_taxonomy_for_object_type()`). |
| `is_hierarchical()`          | `false`                                                | make it page-like.                                                                             |
| `get_menu_position()`        | `null`                                                 | place it in the admin menu.                                                                    |
| `get_labels()`               | name + singular_name                                   | supply the full label set.                                                                     |
| `get_custom_options()`       | `[]`                                                   | merge in any other `register_post_type()` arg.                                                 |
| `get_options()`              | the assembled array                                    | replace the whole args array (rarely needed).                                                  |
| `after_register()`           | no-op                                                  | run setup once the type exists (e.g. rewrite-rule flush logic).                                |

**Defaults worth knowing:** `public`, `has_archive`, `show_in_rest`, and `show_in_menu` are all `true`; `show_in_nav_menus` is `false`. If those don't suit, override `get_custom_options()`.

### AbstractTaxonomy

[`AbstractTaxonomy.php`](../inc/Contracts/Abstracts/AbstractTaxonomy.php) — the taxonomy counterpart, same pattern, also on `init`.

**Must implement:** `get_slug()` (static), `get_object_types()` (static), `get_singular_label()`, `get_plural_label()`.

```php
final class GenreTaxonomy extends AbstractTaxonomy {
    public static function get_slug(): string        { return 'genre'; }
    public static function get_object_types(): array { return [ 'article' ]; }
    public function get_singular_label(): string     { return __( 'Genre', 'my-plugin' ); }
    public function get_plural_label(): string        { return __( 'Genres', 'my-plugin' ); }
}
```

Same override seams as the post type where they apply: `is_hierarchical()`, `get_labels()`, `get_custom_options()`, `get_options()`, `after_register()`. Defaults: `public`, `show_admin_column`, `show_in_rest`, `query_var` all `true`.

> **Two ways to wire a taxonomy to a post type.** Either list it in the post type's `get_supported_taxonomies()`, or set the taxonomy's `get_object_types()`. Both end up calling WordPress correctly; pick one side as the source of truth per project so the association lives in one place.

### AbstractBlock

[`AbstractBlock.php`](../inc/Contracts/Abstracts/AbstractBlock.php) — for **dynamic** (server-rendered) blocks. Registers on `init`.

**Must implement:** `get_name()` (static, namespaced e.g. `my-plugin/hero`), `render( array $attributes, string $content, \WP_Block $block ): string`.

The base always wires your `render()` as the block's `render_callback`. Where the block metadata comes from depends on `get_block_dir()`:

- return an absolute path to a build directory with a `block.json` → registered via `register_block_type( $dir, $args )`;
- return `null` (the default) → registered by name with whatever `get_block_args()` returns (a purely programmatic block).

```php
final class HeroBlock extends AbstractBlock {
    public static function get_name(): string { return 'my-plugin/hero'; }

    protected function get_block_dir(): ?string {
        return MY_PLUGIN_PATH . 'build/hero';
    }

    public function render( array $attributes, string $content, \WP_Block $block ): string {
        return sprintf( '<section class="hero">%s</section>', esc_html( $attributes['title'] ?? '' ) );
    }
}
```

> For **static** blocks (JSON only, no server render), skip this abstract — the class docblock points you at `AssetLoader::register_block_manifest()` instead. See [loaders.md](loaders.md).

### AbstractShortcode

[`AbstractShortcode.php`](../inc/Contracts/Abstracts/AbstractShortcode.php) — registers a shortcode on `init` and gives you a clean, attribute-parsed render method.

**Must implement:** `get_tag()` (static), `render( array $atts, ?string $content ): string`.

The base's `shortcode_callback()` runs your `$atts` through `shortcode_atts( $this->default_atts(), … )` before calling `render()`, so by the time you see them the attributes are merged with your declared defaults.

```php
final class ButtonShortcode extends AbstractShortcode {
    public static function get_tag(): string { return 'button'; }

    protected function default_atts(): array {
        return [ 'href' => '#', 'label' => 'Click' ];
    }

    protected function render( array $atts, ?string $content ): string {
        return sprintf(
            '<a class="btn" href="%s">%s</a>',
            esc_url( $atts['href'] ),
            esc_html( $atts['label'] )
        );
    }
}
```

`render()` is `protected` — it's an implementation detail behind the public `shortcode_callback()`. **You escape your own output;** the framework parses attributes but does not sanitise or escape on your behalf.

---

## REST

### AbstractRESTController

[`AbstractRESTController.php`](../inc/Contracts/Abstracts/AbstractRESTController.php) — extends WordPress core's `WP_REST_Controller` and adds the hook wiring. Registers `register_routes()` on `rest_api_init`.

It pre-fills two properties: `$namespace` (empty — **you must set it**, usually to your plugin/theme slug) and `$version` (`'1'`). A child overrides `$namespace` and implements `register_routes()` to call `register_rest_route()` for each endpoint.

```php
final class ArticlesController extends AbstractRESTController {
    protected $namespace = 'my-plugin';

    public function register_routes(): void {
        register_rest_route(
            "{$this->namespace}/v{$this->version}",
            '/articles',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_items' ],
                'permission_callback' => [ $this, 'get_items_permissions_check' ],
            ]
        );
    }
}
```

Because it's a real `WP_REST_Controller`, all the core helper methods (`get_items_permissions_check()`, schema helpers, …) are available to override.

> **Implementation note.** The base declares `register_routes()` as a concrete method that throws a `LogicException` naming the concrete class and method, rather than as `abstract`. The effect is "you must override it," but the failure surfaces at **runtime** (when `rest_api_init` fires), not at class-load time. Always provide your own `register_routes()`. (An `abstract` method would catch a missing override at class-load time instead of at first request — a deliberate trade-off here.)

---

## Admin & roles

### AbstractAdminPage

[`AbstractAdminPage.php`](../inc/Contracts/Abstracts/AbstractAdminPage.php) — a bare admin menu page. Registers on `admin_menu`.

**Must implement:** `get_slug()` (static), `get_page_title()`, `get_menu_title()`, `render()`.

Override `get_parent_slug()` to choose top-level vs. submenu (it returns `null` by default = a **top-level** menu), plus `get_capability()` (`manage_options`), `get_icon()`, `get_position()`.

```php
final class DashboardPage extends AbstractAdminPage {
    public static function get_slug(): string { return 'my-plugin'; }
    protected function get_page_title(): string { return __( 'My Plugin', 'my-plugin' ); }
    protected function get_menu_title(): string { return __( 'My Plugin', 'my-plugin' ); }
    public function render(): void {
        echo '<div class="wrap"><h1>', esc_html( get_admin_page_title() ), '</h1></div>';
    }
}
```

**You escape everything `render()` echoes.** The framework registers the page; the page body is yours.

### AbstractSettingsPage

[`AbstractSettingsPage.php`](../inc/Contracts/Abstracts/AbstractSettingsPage.php) — the `AbstractAdminPage` pattern plus the Settings API (a parallel class, not a subclass — it re-declares the same menu seams). It registers on **three** hooks: `admin_menu` (the page), `admin_init` (the settings), and `rest_api_init` (so `show_in_rest` settings are registered for the block editor / REST too).

**Must implement:** `get_slug()` (static), `get_page_title()`, `get_menu_title()`, `get_settings()`, `render()`.

`get_settings()` returns a map of `option_name => register_setting() args`; the base loops it and calls `register_setting( $this->get_option_group(), … )` for each. The option group defaults to the page slug.

`get_menu_slug()` also defaults to the page slug and can be overridden when the menu slug depends on instance state. The base always filters `option_page_capability_{group}` for this page's option group to return `get_capability()`, so the menu, the render callback, and the `options.php` save are authorized by the same capability. That matters when `get_capability()` is lowered from its `manage_options` default: without the filter the page would render for the lower capability but silently fail to save.

```php
final class SettingsPage extends AbstractSettingsPage {
    public static function get_slug(): string { return 'my-plugin-settings'; }
    protected function get_page_title(): string { return __( 'Settings', 'my-plugin' ); }
    protected function get_menu_title(): string { return __( 'Settings', 'my-plugin' ); }

    protected function get_settings(): array {
        return [
            'my_plugin_api_key' => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => false,
            ],
        ];
    }

    public function render(): void { /* settings_fields() + do_settings_sections() + form */ }
}
```

Unlike `AbstractAdminPage`, this one defaults `get_parent_slug()` to `'options-general.php'` — i.e. it lands under **Settings** as a submenu by default. Return `null` for a top-level page.

### AbstractUserRole

[`AbstractUserRole.php`](../inc/Contracts/Abstracts/AbstractUserRole.php) — a **versioned** custom role. Roles are persisted in the database via `add_role()`, so naively calling `add_role()` on every request is wasteful and re-adding a changed role is a no-op (WordPress ignores `add_role()` if the role exists). This abstract solves both with a stored version number.

**Must implement:** `get_slug()` (static), `get_display_name()`, `get_capabilities()`, `get_version()`.

It hooks `admin_init` → `maybe_update_role()`, which compares `get_version()` to a stored option and, only when your version is higher, removes and re-adds the role with fresh capabilities, then bumps the stored version. So: **change the capabilities, increment `get_version()`, and the role updates itself** on the next admin request.

```php
final class EditorPlusRole extends AbstractUserRole {
    public static function get_slug(): string { return 'editor_plus'; }
    protected function get_display_name(): string { return 'Editor Plus'; }
    protected function get_version(): int { return 2; }   // bump when caps change
    protected function get_capabilities(): array {
        return [ 'read' => true, 'edit_posts' => true, 'manage_categories' => true ];
    }
}
```

Two things to keep in mind:

- The update runs on `admin_init`, so the role refreshes the next time **an admin area request happens**, not the instant you deploy. For activation-time registration, call your role's logic from a plugin activation hook as well.
- `remove_role()` is provided for deactivation/uninstall — it drops the role and deletes the version option. Call it from your uninstall path; the framework won't.

---

## Feature flags

### AbstractFeature

[`AbstractFeature.php`](../inc/Contracts/Abstracts/AbstractFeature.php) — the odd one out. It doesn't register a WordPress object of its own; it gates a subclass's **own** registration behind a feature flag. It's the only abstract that `implements ConditionallyRegistrable`, so the [`Loader`](architecture.md) still instantiates the class (its constructor runs) but calls `can_register()` before `register_hooks()` — skipping only the hook registration when the flag is off.

Two jobs, both automatic:

- **Discovery.** On construction — which runs even when the flag is off — it registers its slug, name, and description into a shared [`FeatureSelector`](utilities.md#featureselector) registry, so the flag shows up on the settings page without any extra wiring.
- **Gating.** `can_register()` returns the registry's `is_enabled( $slug )` (an instance call via `get_feature_registry()`), so the subclass's `register_hooks()` runs only when the flag is enabled.

**Must implement:** `get_slug()`, `get_feature_registry()` (return the shared registry), and — since `AbstractFeature` deliberately leaves it out — the subclass's own `register_hooks()` with the actual feature behavior. Optionally override `get_name()` (defaults to a title-cased slug — `author-bio` → `Author Bio`) and `get_description()` (defaults to empty).

Because every feature in a project shares one registry, the usual shape is a thin consumer-side base that supplies it, then one small class per feature:

```php
// One shared registry for the whole plugin.
abstract class Feature extends AbstractFeature {
    protected function get_feature_registry(): FeatureSelector {
        return MyPlugin::feature_selector(); // the shared instance
    }
}

final class AuthorBio extends Feature {
    protected function get_slug(): string { return 'author-bio'; }
    protected function get_description(): string {
        return __( 'Show an author bio box beneath each post.', 'my-plugin' );
    }

    public function register_hooks(): void {          // runs only when the flag is on
        add_filter( 'the_content', [ $this, 'append_bio' ] );
    }
}
```

The feature now appears on the `FeatureSelector` settings page as "Author Bio" with that description, and its `the_content` filter attaches only while the flag is enabled. See [utilities.md](utilities.md#featureselector) for the registry and its settings page.

## Abilities (WordPress 6.9+)

### AbstractAbility

[`AbstractAbility.php`](../inc/Contracts/Abstracts/AbstractAbility.php) — one WordPress Abilities API ability. The subclass declares _what_ the ability is — name, label, description, category, schemas, and `execute()` — and `args()` maps those to the argument array `wp_register_ability()` expects. The class is deliberately **not** `Registrable`: abilities may only be registered inside the API's own init hook, so the paired registrar (below) owns that timing and an ability stays a plain describable object.

**Must implement:** `name()` (the full `"my-plugin/do-thing"` identifier — lowercase, one slash), `label()`, `description()`, `category()` (the slug of a category the registrar registers), `input_schema()` / `output_schema()` (JSON Schema arrays; return `[]` to omit the key), and `execute( mixed $input ): mixed`.

Overridable seams:

- `permission( mixed $input = null )` — the permission gate. Defaults to `current_user_can( 'manage_options' )`: fail-closed, administrators only.
- `meta()` — defaults to `[]`, which keeps the API defaults: not exposed over REST, no MCP flag. Exposure is always an explicit opt-in.

Both callbacks in `args()` are closure-wrapped with a defaulted parameter because core invokes them with **no arguments** when the ability declares no input schema.

### AbstractAbilityRegistrar

[`AbstractAbilityRegistrar.php`](../inc/Contracts/Abstracts/AbstractAbilityRegistrar.php) — the `Registrable` that registers a group of abilities. Its `register_hooks()` attaches two actions — `wp_abilities_api_categories_init` (registers the shared category) and `wp_abilities_api_init` (registers each ability) — the only hooks those registrations are legal on. Core fires both lazily on first registry access. On cores older than 6.9 the hooks never fire, so the registrar is simply inert and the package's 6.5 floor is unchanged.

**Must implement:** `category_slug()`, `category_description()` (the API rejects a category without a non-empty description), and `abilities()` — the `AbstractAbility[]` to register.

Overridable: `category_label()` (defaults to the title-cased slug) and `before_register()` (an empty hook that runs once before the abilities register — ensure storage exists, prime options). Category registration is idempotent — a registrar skips the call when the slug already exists — so several registrars can share one category and load in any order.

The usual shape mirrors `AbstractFeature`: a thin consumer-side base supplies the shared category slug, one class per ability, one registrar naming them:

```php
// One shared category for the whole plugin.
abstract class Ability extends AbstractAbility {
    protected function category(): string { return 'my-plugin'; }
}

final class SiteSummary extends Ability {
    public function name(): string { return 'my-plugin/site-summary'; }
    protected function label(): string { return 'Site Summary'; }
    protected function description(): string {
        return 'Returns published post counts for the site.';
    }
    protected function input_schema(): array { return []; }
    protected function output_schema(): array { return [ 'type' => 'object' ]; }

    public function execute( mixed $input ): mixed {
        return [ 'posts' => (int) wp_count_posts()->publish ];
    }
}

final class Registrar extends AbstractAbilityRegistrar {
    protected function category_slug(): string { return 'my-plugin'; }
    protected function category_description(): string {
        return 'Read-only insights about the site.';
    }
    protected function abilities(): array { return [ new SiteSummary() ]; }
}
```

Load `Registrar` like any other `Registrable` (usually from a module's `get_classes()`); the ability is then retrievable via `wp_get_ability( 'my-plugin/site-summary' )` and executable by administrators.

---

Next: [loaders.md](loaders.md) for the asset and template machinery, or back to [architecture.md](architecture.md) for how all of these get loaded.
