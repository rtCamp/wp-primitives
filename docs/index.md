---
sidebar_position: 1
sidebar_label: Overview
---

# wp-primitives

`rtcamp/wp-primitives` is the shared PHP base that every rtCamp plugin and theme
skeleton is built on. It is a Composer **library**, not a plugin: it ships a set
of contracts (interfaces, abstracts, traits) plus concrete loaders and
utilities, and the skeletons consume it through `vendor/`.

Two rules define the whole package:

- **Zero runtime dependencies.** `composer.json` `require` holds only `php`
  (8.2+). Everything else is `require-dev`.
- **`inc/Contracts/` is public API.** Every interface, abstract, and method
  signature under it is consumed by every skeleton, so changes there are treated
  as breaking.

## What's in here

1. **A registration system.** A predictable way to turn a list of classes into
   live WordPress hooks — `Registrable`, the `Loader` trait, and the
   `Container`. This is the spine; start with
   [getting-started.md](getting-started.md), then read
   [architecture.md](architecture.md).
2. **A library of base classes.** Twelve `Abstract*` classes — most wrap one
   WordPress registration chore (a post type, a taxonomy, a block, a settings
   page, an ability, …); two are structural: `AbstractModule` groups services
   and `AbstractFeature` gates one behind a flag. See
   [abstracts.md](abstracts.md).
3. **Asset & render plumbing.** `AssetLoader`, `ComponentLoader`, and
   `TemplateLoader` — enqueue built assets and resolve component/template files
   across the child-theme → parent-theme → package hierarchy. See
   [loaders.md](loaders.md).
4. **Utilities & services.** Context-scoped helpers a consumer holds or shares:
   `Encryptor`, `Cache`, `FeatureSelector` (+ its settings page), `Logger`,
   `Transients`, and `Timer`. See [utilities.md](utilities.md).

## Map of the docs

| Doc | What it covers |
|---|---|
| [getting-started.md](getting-started.md) | A complete first integration: requirements, bootstrap, module, service, and shared-service retrieval. |
| [architecture.md](architecture.md) | The mental model: how a class becomes a live hook. The `Registrable` → `Loader` → `Container` flow and where `Module` fits. Start here. |
| [contracts.md](contracts.md) | Reference for the interfaces and traits: `Registrable`, `ConditionallyRegistrable`, `Shareable`, `CLICommand`, `Loader`, `Singleton`. |
| [abstracts.md](abstracts.md) | Cookbook for the twelve `Abstract*` base classes — what each is for, the methods to implement, the hook it wires, a minimal subclass. |
| [loaders.md](loaders.md) | `AssetLoader`, `ComponentLoader`, `TemplateLoader` — the asset/render subsystem and the theme-override hierarchy they share. |
| [utilities.md](utilities.md) | `Encryptor`, `Cache`, `FeatureSelector`, its settings page, `Logger`, `Transients`, `Timer`, and `Container`. |
| [upgrading.md](upgrading.md) | What changes between releases, and what a consumer has to do about it. |
| [troubleshooting.md](troubleshooting.md) | Symptom → cause for the exceptions, `_doing_it_wrong()` notices, and silent no-ops the framework emits. |
| [ai-review-system.md](ai-review-system.md) | How the AI review instructions are authored here and synced into the skeletons. *(maintainer/tooling doc)* |
| [maintainers.md](maintainers.md) | How to set up, test, verify, and document changes to this repository. *(maintainer doc)* |

## How a skeleton uses it (the one-paragraph version)

A skeleton's `Main` class uses the `Loader` trait and hands it a list of class
names — usually a list of `Module`s. Each `Module` is itself a `Loader` that
holds a list of services. Loading walks the list: every class is instantiated,
anything that is `Registrable` gets its `register_hooks()` called (so it wires
its own `add_action`/`add_filter`), and anything marked `Shareable` is cached in
a `Container` so it can be fetched later. The `Abstract*` classes are all
`Registrable` — they exist so the service author writes "this is a post type
called *foo*" instead of hand-writing the `register_post_type()` call and the
`init` hook. That's the entire framework in one breath; the rest is detail.
