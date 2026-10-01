---
sidebar_position: 9
sidebar_label: Upgrading
---

# Upgrading

What changes between releases of `rtcamp/wp-primitives`, and what a consuming
plugin or theme has to do about it. [`CHANGELOG.md`](../CHANGELOG.md) is the
complete per-release record; this page carries only the entries that require a
code change on the consumer side.

## Versioning promise

The package follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html),
and the surface that version numbers describe is `inc/Contracts/` — every
interface, abstract, trait, and public method signature under it.

| Change | Version bump | Consumer impact |
|---|---|---|
| Renamed or re-signatured member of `inc/Contracts/` | major | Subclasses must be updated. |
| Removed public method or class anywhere in `inc/` | major | Callers must be updated. |
| New abstract, interface, utility, or optional method | minor | None; adopt when useful. |
| New `abstract` method on an existing abstract | major | Every subclass must implement it. |
| Behavior fix inside an existing method | patch | Usually none — read the entry. |

Pin with `^2.0` so Composer takes minors and patches and refuses the next major.
Read this page and the changelog before widening a constraint across a major.

## Upgrade routine

```bash
composer update rtcamp/wp-primitives
composer lint && composer analyse   # in the consuming package
```

Then run the consumer's own test suite. Static analysis catches the majority of
contract breaks (a missing abstract implementation, a changed signature) before
runtime does.

## 1.0.0 → 1.0.1

**Affects:** any class using the `Singleton` trait.

1.0.0 stored singleton instances in a private, class-string-keyed map. 1.0.1
restored the ecosystem-standard `protected static $instance` storage, written by
`get_instance()` once the constructor returns.

Two consequences:

- **Early self-assignment works again, and is the supported pattern.** A
  constructor that does work able to re-enter `get_instance()` — a `Main` that
  loads classes whose constructors call `Main::get_instance()` — must publish
  itself first:

  ```php
  protected function __construct() {
      static::$instance = $this;   // before any work that can re-enter
      $this->load( [ ContentModule::class ] );
  }
  ```

  This follows the immediate-loading pattern in
  [getting started](getting-started.md#3-bootstrap-the-loader): the entry point
  calls `Main::get_instance()`, and the constructor loads the modules.

  On 1.0.0 this fataled with *"Access to undeclared static property"*. If that
  line was removed as a 1.0.0 workaround, restore it.

- **A class and its subclasses share one storage slot.** This is the trade-off
  of the trait's single static property, and it is documented on the trait. Do
  not call `get_instance()` on a subclass of a class that uses `Singleton` —
  whichever side resolves first occupies the slot for both. Give each singleton
  its own `use Singleton;`, or prefer `Shareable` + `get_shared()`.

No other 1.0.1 change is consumer-visible. See
[contracts.md](contracts.md#singleton) for the full trait reference.

## When an upgrade breaks something

Framework failures are mostly loud — see
[troubleshooting.md](troubleshooting.md) for the symptom → cause table, then the
changelog entry for the release you moved to.
