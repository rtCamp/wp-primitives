# Copilot instructions — wp-primitives

`rtcamp/wp-primitives`: shared base contracts (interfaces, abstracts, traits) and small utilities consumed via Composer by every rtCamp plugin/theme skeleton. **Zero runtime dependencies.** PHP 8.2+.

This repo defines the framework itself. The rules for *consuming* it live in `ai/primitives-php.instructions.md` (shipped to consumers); keep it in sync when contracts change.

Detailed rules: `.github/instructions/php.instructions.md`.

## Stack

- PHP 8.2+, PSR-4 `rtCamp\WPPrimitives\` → `inc/`; tests `rtCamp\WPPrimitives\Tests\` → `tests/`.
- PHPUnit; PHPCS (WordPress-Core/Extra/Docs + VIPCS); PHPStan. Zero errors before merge.
- `require` in `composer.json` holds **only** `php`. Everything else is `require-dev`.

## Universal rules

- **TDD**: failing PHPUnit test first (`tests/` mirrors `inc/`), then code.
- **`inc/Contracts/` is public API.** Interfaces, abstracts and their method signatures are consumed by every plugin/theme: a signature change breaks all of them. Treat changes as breaking.
- When you change a contract, update `ai/primitives-php.instructions.md` so the consumer review rules stay accurate.
- No runtime dependencies. Prefer official WordPress / PHP stdlib.

## Review conduct

- **One comment per distinct issue** Distinct problems get separate comments, even on the same line. A single root cause spanning lines → posted once at the clearest line.
- **Always post the missing-tests finding** when a new/changed class ships without a matching test in `tests/`. Mandatory; exempt from dedupe/trim/comment-budget.
- **Order by impact:** backward-compatibility/contract break → correctness → security → style.
- **On a correct, deliberate implementation, don't manufacture findings.** Prefer zero comments over low-value ones. A clean PR comes back clean.
