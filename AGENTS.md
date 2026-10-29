# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.

## Project Overview

**nette/component-model** is the foundational component architecture for the Nette
Framework: a hierarchical system where components nest inside containers, are
monitored for attach/detach events, and are addressed through a path-based lookup.
Consumed by Forms and Application, not usually used directly.

- **PHP Version**: 8.1 - 8.6
- **Package**: `nette/component-model`

Five small files, but a genuinely tricky core (tree + monitoring + lookup +
cloning). Almost all the value is in `Component::refreshMonitors()` and the
cloning handshake.

## Essential Commands

```bash
# Run all tests
composer tester        # or: vendor/bin/tester tests -s

# Run a single test file
php tests/ComponentModel/Container.getComponentTree.phpt

# Static analysis (PHPStan level 8)
composer phpstan
```

## Conventions

- Every file starts with `declare(strict_types=1);`; the coding style is checked
  by DressCode (`dresscode.neon`).
- Tests are Nette Tester `.phpt` files; they usually define minimal component
  classes inline.
- This is the 3.x branch: deprecated APIs and back-compatibility paths
  (`@deprecated`, `@property-deprecated`, `// back compatibility`) stay working
  here. Do not remove them.

## Working in this repo

- **`Component::$monitors` is one array for two things:** the `lookup()` cache
  (ancestor, depth, path) and the handlers registered by `monitor()`; `unmonitor()`
  drops both. A parent change walks the subtree depth-first, children before their
  parent, and invalidates the cache selectively: on attach, entries with a found
  ancestor stay (it lies inside the moved subtree) and the rest is dropped or
  looked up again; on detach, only types whose ancestor lies above the detachment
  point are cleared. So a root cached by `lookup(null)` goes stale when its
  subtree is attached higher.
- **Handlers are only collected during that walk** and run once it finishes,
  descendant-first for both `attached` and `detached`, each (callback, ancestor)
  pair once. A throwing handler leaves the change half-done: `addComponent()`
  drops the component from the container, but its `getParent()` still points there.
- **Cloning re-homes children via a cross-instance handshake:** `Container::__clone`
  sets `$cloning` on the original container and the children's `__clone` asks it
  through `_isCloning()`, not via a parameter. Standalone-cloning a child detaches it.
- **A component name cannot contain `-`** (`#^[a-zA-Z0-9_]+$#`) because `-` is the
  path separator; `getComponent('a-b-c')` descends into sub-containers.
- **Components cannot be serialized** (`__serialize` throws by design).
- **`getComponent()` return-type inference lives in `nette/phpstan-rules`**
  (`GetComponentReturnTypeExtension`), not in phpDoc here - look there for types.
