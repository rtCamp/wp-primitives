---
applyTo: "inc/**/*.php"
description: "Framework-development rules for rtcamp/wp-primitives PHP."
---

# Framework PHP rules

## Layout & contracts

- `inc/Contracts/Interfaces/`: `Registrable`, `ConditionallyRegistrable`, `Shareable`, `CLICommand`.
- `inc/Contracts/Abstracts/`: `AbstractModule`, `AbstractFeature`, `AbstractPostType`, `AbstractTaxonomy`, `AbstractBlock`, `AbstractShortcode`, `AbstractRESTController`, `AbstractSettingsPage`, `AbstractAdminPage`, `AbstractUserRole`, `AbstractAbility`, `AbstractAbilityRegistrar`.
- `inc/Contracts/Traits/`: `Loader`, `Singleton`.
- `inc/` root: `Container`, `AssetLoader`, `ComponentLoader`, `TemplateLoader`; `inc/Utils/`: utilities (e.g. `Encryptor`).

Everything under `inc/Contracts/` is a **consumed contract**. New abstracts/interfaces must follow the existing shape (e.g. an `Abstract*` `implements Registrable` and exposes `abstract` methods for the bits that vary).

## Mandatory

- `declare( strict_types = 1 );` at the top of every file. Full param + return types. `@package` + `@since` on every class/trait/interface docblock.
- `snake_case` methods, `PascalCase` classes, filename === class, namespace === directory (PSR-4).
- `static::`, never `self::`, for late static binding (the `Singleton` trait relies on it).
- New class/trait/interface gets a PHPUnit test in `tests/` mirroring its path.

## Flag on review (priority order)

1. 🚩 **MANDATORY: always post, exempt from dedupe/trim/budget:** a new or changed class/trait/interface shipped without a matching test in `tests/`.
2. 🚩 Backward-incompatible change to anything under `inc/Contracts/` (renamed/removed/retyped public method, changed abstract signature) without a documented migration. This breaks every consumer.
3. 🚩 A new dependency added to `composer.json` `require` (must stay `php`-only; dev tools go in `require-dev`).
4. 🚩 Missing `strict_types`/types/docblocks; PSR-4 mismatch; `self::` where `static::` is required.
5. 🚩 Missing escape/sanitize where the utility touches WordPress output/input; raw `$wpdb` without `prepare()`.
6. 🚩 New abstract/interface that doesn't follow the existing contract shape (e.g. an `Abstract*` not implementing `Registrable`, or duplicating a capability the `Loader`/`Container` already provides).
