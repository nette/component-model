# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.

## Documentation

Any distilled, agent-facing documentation for this package - how it works
internally and the rationale behind key design decisions - lives in `docs/`.
Consult it before non-trivial changes; it is the source of truth from which the
public manual is distilled.

Five small files, but a genuinely tricky core (tree + monitoring + lookup +
cloning). Almost all the value is in `Component::refreshMonitors` and the cloning
handshake - read `docs/internals.md` before touching them.

## Project Overview

**nette/component-model** is the foundational component architecture for the Nette
Framework: a hierarchical system where components nest inside containers, are
monitored for attach/detach events, and are addressed through a path-based lookup.
Consumed by Forms and Application, not usually used directly.

- **PHP Version**: 8.3 - 8.5
- **Package**: `nette/component-model`

## Essential Commands

```bash
# Run all tests
composer tester        # or: vendor/bin/tester tests -s

# Run a single test file
php tests/ComponentModel/Container.getComponents.phpt

# Static analysis (PHPStan level 8)
composer phpstan
```

## Conventions

- Every file starts with `declare(strict_types=1);`; Nette Coding Standard.
- Tests are Nette Tester `.phpt` files; they usually define minimal component
  classes (Button, ComponentX) inline. `tests/bootstrap.php` sets up autoloading
  and provides a `Notes` helper for recording lifecycle events during a test.

## Working in this repo

- **Monitoring is a single-pass, live-mutation-safe walk with a precise order:**
  `attached` fires top-down (ancestor→descendant), `detached` fires bottom-up, and
  detach is depth-gated. The `$monitors` array is a per-type cache holding *both*
  the `lookup()` result and the registered callbacks. This is the sharp edge -
  see `docs/internals.md` and the ADR it references before changing it.
- **Cloning re-homes children via a cross-instance handshake** (`Container::$cloning`
  / `_isCloning()`), not a parameter. Standalone-cloning a child detaches it.
- **A component name cannot contain `-`** (`#^[a-zA-Z0-9_]+$#`) because `-` is the
  path separator; `getComponent('a-b-c')` descends into sub-containers.
- **Components cannot be serialized** (`__serialize` throws by design).
- **`getComponent()` return-type inference lives in `nette/phpstan-rules`**
  (`GetComponentReturnTypeExtension`), not in phpDoc here - look there for types.
