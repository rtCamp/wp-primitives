---
sidebar_position: 11
sidebar_label: AI review system
---

# AI review & instructions system

How GitHub Copilot code-review instructions and AI-agent guidance are authored, distributed, and kept in sync across the framework, the plugin/theme skeletons, and assembled `wp-content` projects.

## The problem it solves

1. **Copilot code review only reads instruction files from the *repo root* `.github/`.** It ignores nested `.github/` directories.
2. Our code lives in **three kinds of repo**, with **two different roots**:
   - a **standalone plugin/theme** repo, where the root is the package;
   - an **assembled `wp-content`** repo, where the root is `wp-content` and packages are nested under `plugins/<slug>/` and `themes/<slug>/`.
3. The framework is a **Composer dependency** of the packages (lands in each package's gitignored `vendor/`), so it is **invisible at review**. Its contract rules must be embedded in the consumers.
4. The same rules must reach **two audiences**: Copilot **code review** (`.github/`) and **AI coding agents** (Claude Code, Copilot coding agent, Codex, via `AGENTS.md` / `CLAUDE.md`).

## The pieces

| File | Repo | Role |
|---|---|---|
| `ai/primitives-php.instructions.md` | framework | Canonical framework + WordPress review rules. Single source of truth. |
| `bin/sync-ai-instructions.js` | framework | One tool, run by the consumer via `npm run sync-ai`. Refreshes each package's framework rules from its vendored copy, then projects every package's instructions to the `wp-content` root. `--check` for a CI drift gate; `--root DIR` forces the `wp-content` root instead of detecting it from the current directory. |
| `.github/copilot-instructions.md` | each package | Repo-wide overview + review conduct (skeleton-authored). |
| `.github/instructions/structure.instructions.md` | each package | Package layout + wiring (skeleton-authored, carries the package's names). |
| `.github/instructions/primitives-php.instructions.md` | each package | Generated from the package's vendored framework. Banner-marked; do not hand-edit. |
| `AGENTS.md` | each package | Tool-agnostic brief for coding agents. Inlines key principles + points to `.github/`. |
| `CLAUDE.md` | each package | Thin; defers to `AGENTS.md`, holds any Claude-only overrides. |

## Data flow

```
framework  ai/primitives-php.instructions.md   (edit rules here)
   |
   |  composer require/update  ->  lands in each package's vendor/
   v
plugins/<slug>/vendor/rtcamp/wp-primitives/ai/primitives-php.instructions.md
   |
   |  npm run sync-ai (chained from npm run init)  ->  bin/sync-ai-instructions.js
   |     step 1 (refresh): copy vendored rules into the package's .github/instructions/
   |     step 2 (project): walk up to the wp-content root, write every package's
   |                       instructions with applyTo re-scoped to that root
   v
plugins/<slug>/.github/instructions/{primitives-php,structure}.instructions.md   (committed)
wp-content/.github/instructions/
   primitives-php.instructions.md        applyTo: plugins/a/**/*.php,plugins/b/**/*.php,themes/c/**/*.php   (MERGED)
   <slug>-structure.instructions.md     applyTo: plugins/<slug>/inc/**                                      (per package)
```

- **Identical** files shared by **two or more** packages (the framework rules) merge into **one** file with a combined `applyTo`. (In a single-package project even the framework rules have only one member, so they emit per-package as `<slug>-<name>`, like the unique files below.)
- **Unique** files (each package's `structure`, which carries its own names) are emitted **per package** as `<slug>-<name>`.
- A **standalone** package (no `wp-content` root above) gets step 1 only; the package's own `.github/` is what Copilot reads.

## Names vs globs — two transforms

| Transform | Done by | When |
|---|---|---|
| Placeholder names (`Project_Name` to real namespace, `project-name` to slug) | `bin/init.js` in the skeleton (also processes `.github/`) | `npm run init` |
| Refresh framework rules + re-glob `applyTo` to the wp-content root + merge | `bin/sync-ai-instructions.js` (framework) | `npm run sync-ai` (chained from init) |

## Two review contexts, one source

| Context | Repo root | Copilot reads | Globs |
|---|---|---|---|
| Standalone plugin/theme | the package | the package's own `.github/` | root-relative (`**/*.php`, `inc/**`) |
| Assembled `wp-content` | `wp-content` | `wp-content/.github/` (projected) | prefixed (`plugins/<slug>/**`) |

The framework repo itself has its **own** `.github/` with framework-*development* rules; reviewed when you PR the framework, and it does not flow into consumers (it's a vendored dependency, invisible at consumer review).

## Using it

- **Assemble a project**: drop packages into `wp-content/{plugins,themes}/`, then `composer install` and `npm install && npm run init`. `init` sets names and runs `sync-ai`, which refreshes each package's framework rules and writes the wp-content root `.github/`.
- **Add another plugin later**: run `npm run sync-ai`; it discovers the new package, refreshes it, and merges it into the projected output.
- **Remove a plugin**: run `npm run sync-ai` after removing it; the merged `applyTo` globs are recomputed and now-orphaned generated files are pruned (only files carrying the `GENERATED` banner are deleted).
- **Change a shared rule**: edit `ai/primitives-php.instructions.md` in the framework, publish, then in the project run `composer update` + `npm run sync-ai`.
- **Change a package-specific rule**: edit that package's `.github/instructions/structure.instructions.md`, then `npm run sync-ai`.
- **CI gate**: `npm run sync-ai -- --check` fails the build if the committed instructions are stale. Copilot reads instructions from the **base branch**, so keeping `main` current is what matters.

## Constraints & guards

- **~4000 characters per instruction file**: Copilot only reads the first ~4000. The banner is appended after the rules so it never eats the window; the tool warns (STDERR) when the rules themselves exceed it. Keep `ai/primitives-php.instructions.md` lean.
- **`applyTo` accepts comma-separated globs**: relied on by the merge.
- Generated files carry a `GENERATED` banner; never hand-edit them. Edit the source in the framework (shared rules) or the skeleton (structure).
- Third-party plugins in `wp-content` are not governed by these rules; exclude them from review via Copilot content-exclusion settings if needed.
