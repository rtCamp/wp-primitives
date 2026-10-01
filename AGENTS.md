# AGENTS.md — wp-primitives

Tool-agnostic brief for AI coding agents (Claude Code, Copilot coding agent, Codex). `rtcamp/wp-primitives`: shared base contracts (interfaces, abstracts, traits) and small utilities consumed via Composer by every rtCamp plugin/theme skeleton. **Zero Composer runtime dependencies.** PHP 8.2+, WordPress 6.5+ — the floor is set by the Script Modules API (`wp_register_script_module()`, new in 6.5). The APIs used above 6.5 are `wp_register_block_types_from_metadata_collection()` (6.8+), which `AssetLoader::register_block_manifest()` guards with a per-block fallback for 6.5–6.7, and the Abilities API (6.9+), which the ability abstracts reach only through `wp_abilities_api_*` hooks that never fire on older cores. `Encryptor` requires the OpenSSL PHP extension when used.

## Authoritative rules

- `.github/instructions/php.instructions.md`: framework-development rules and review flags.
- `.github/copilot-instructions.md`: overview + review conduct.

## Key principles (full detail in the files above)

- **`inc/Contracts/` is public API.** Interfaces, abstracts, and their method signatures are consumed by every plugin/theme: a signature change breaks all of them. Treat such changes as breaking.
- **Zero Composer runtime deps**: `composer.json` `require` holds only `php`; everything else is `require-dev`.
- **TDD**: failing PHPUnit test first (`tests/` mirrors `inc/`), then code.
- **Tests run against real WordPress via wp-env** — no WP function mocking. `npm run wp-env start` then `npm run test:php` (a `pretest:php` hook runs `composer install` in the container first). WP-dependent tests extend `rtCamp\WPPrimitives\Tests\TestCase` (a `WP_UnitTestCase`); pure-logic tests can stay on `PHPUnit\Framework\TestCase`. CI runs a PHP × WP matrix (PHP 8.2+, WP 6.5+).
- `declare( strict_types = 1 );`, full types, `@package`/`@since`, `static::` not `self::`, PSR-4 (`rtCamp\WPPrimitives\` → `inc/`).
- **When you change a contract, update `ai/primitives-php.instructions.md`**: the rules file shipped to consumers and synced into their `.github/` by `bin/sync-ai-instructions.js` (`npm run sync-ai`).

## Structure

`inc/Contracts/{Interfaces,Abstracts,Traits}/` (the consumed contract surface), `inc/` root (`Container`, `AssetLoader`, `ComponentLoader`, `TemplateLoader`), `inc/Utils/`. `ai/` holds the canonical consumer instruction doc; `bin/` holds the sync tool.

## This repo also ships tooling for consumers

- `ai/primitives-php.instructions.md`: the canonical framework+WordPress review rules.
- `bin/sync-ai-instructions.js`: run from a consumer via `npm run sync-ai` (chained from `npm run init`). Refreshes each package's `primitives-php.instructions.md` from its vendored copy of this doc, then projects every package's instructions up to the `wp-content` repo root for Copilot review. Standalone packages get the refresh only. `--check` for CI.
