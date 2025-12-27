# ComponentModel internals

Five small files, but a genuinely tricky core — the classic "small package,
sharp edges". This is one coherent mechanism (tree + monitoring + lookup +
cloning), so one file. The value is almost entirely in `Component::refreshMonitors`
and the cloning handshake; the rest of the API is readable from its signatures.

## The `$monitors` array is a triple-purpose, per-type cache

`Component::$monitors` is keyed by **type** — a `class-string` **or `''`** (the
key used for the "find the root" / null-type case; `lookup()` does `$type ??= ''`)
— and each entry is a 4-tuple:

```
$monitors[$type] = [foundAncestor, depthToAncestor, pathToAncestor, [attachedCbs, detachedCbs]]
```

So a single structure holds **both** the cached `lookup()` result (`[0..2]`) **and**
the registered `monitor()` callbacks (`[3]`). `lookup()` fills the cache lazily and
walks parents to find the closest ancestor of the type (or the root when the type
is empty), storing the path and depth. The **depth** is not decoration: detachment
uses it (below).

## Top-down attach, bottom-up detach — the central invariant

`setParent()` drives `refreshMonitors()`, whose parameter `$missing` doubles as the
mode switch: **an array means attaching, `null` means detaching.** Within one
`refreshMonitors` call the order of the three blocks encodes the whole contract:

1. **attach** processes **this node's** monitors (fires `attached`) — *before*
   recursing;
2. it then recurses into child components;
3. **detach** processes this node's monitors (fires `detached`) — *after* the
   child recursion.

Therefore **attach notifications fire ancestor→descendant (top-down)** and
**detach fires descendant→ancestor (bottom-up)** — symmetric, and emergent only
from that block ordering. (The rationale is the promoted ADR
`docs/decisions/2025-12-26 top-down-monitor-notifications.md`; this file states the
*what*, the ADR the *why*.)

Three properties of this live, single-pass algorithm are the traps:

- **No collection phase — listeners may mutate the tree mid-walk.** The recursion
  guards against it: `$processed` (keyed by `spl_object_id`) prevents re-entry, and
  each child is re-checked with **`$component->getParent() === $this`** because a
  previous sibling's listener may already have removed it.
- **Callbacks are deduplicated by `[callback, spl_object_id($ancestor)]`** (the
  `$called` accumulator), so the same handler fires **once per ancestor object**
  even if reached by multiple routes.
- **Detach is depth-gated.** Only monitors whose cached ancestor was **deeper than
  the detachment point** (`$inDepth > $depth`) fire `detached` — an ancestor
  shallower than where the subtree was cut is still reachable and must **not**
  notify. This is why `lookup()` caches the depth.

`monitor()` itself fires `attached` **immediately** if the ancestor already exists
at registration time, and dedups the attach callback on registration.

## Cloning: the container flags itself, children re-home to the clone

Cloning a subtree must re-parent every cloned child onto the cloned container, and
it is done with a cross-instance handshake rather than a parameter:

- **`Container::__clone`** takes the original container (`reset($components)->getParent()`),
  sets **`$original->cloning = $this`** (the new clone), clones each child, then
  clears the flag. The flag lives on **`Container`** (`$cloning`), read via the
  `@internal final _isCloning()`.
- **`Component::__clone`** of each child does `$this->parent = $this->parent->_isCloning()`
  — if the old parent is mid-clone it re-homes to the **new** clone; if
  `_isCloning()` returns `null` (the parent is *not* cloning, i.e. this is a
  standalone clone of a child) it **detaches** and refreshes monitors.

So the direction of the wiring is: the parent announces "I am cloning, here is my
replacement," and each child, as PHP clones it, asks and redirects itself. The
`cloning` flag is only valid for the duration of the child-clone loop.

Components **cannot be serialized** — `__serialize` throws by design.

## `getComponents()` vs `getComponentTree()`, and why names can't be a flat map

- **`getComponents()` returns immediate children only.** Passing the old recursive
  or filter arguments now throws `DeprecatedException` (checked via
  `func_get_args`).
- **`getComponentTree()` returns a flattened depth-first `list`** — deliberately a
  list, not a name-keyed map, because **component names are unique only within
  their container, not across the whole tree**, so a keyed flatten would silently
  drop collisions.
- **Return-type inference for `getComponent()` lives in `nette/phpstan-rules`**
  (`GetComponentReturnTypeExtension` / `ComponentTreeResolver`), not in phpDoc
  here — an agent chasing types should look there.

## Names, paths, and the factory

- **`NameSeparator` is `-`**, and a component name must match `#^[a-zA-Z0-9_]+$#`
  — so a name **cannot contain `-`**, because `-` is the *path* separator.
  `getComponent('a-b-c')` splits on it and descends into sub-containers.
- **Lazy factory:** `getComponent($name)` auto-creates via a `createComponent<Ucfirst>()`
  method, guarded so the name's case must match (a leading-uppercase `$name` will
  not trigger it) and the reflected method name matches exactly.
- **`addComponent` is exception-safe:** it registers the child, then calls
  `setParent` inside a `try/catch` that **undoes the registration** if `setParent`
  throws (e.g. a `validateParent` veto). It also walks ancestors to reject circular
  references.

## Navigation map

| Concern | Where |
|---|---|
| Per-type lookup+callback cache | `Component::$monitors`, `lookup`, `lookupPath` |
| Attach/detach ordering & guards | `Component::refreshMonitors` |
| Register listeners | `Component::monitor`/`unmonitor` |
| Clone re-homing handshake | `Container::__clone`/`_isCloning`, `Component::__clone` |
| Immediate vs full tree | `Container::getComponents`/`getComponentTree` |
| Factory, path descent, add safety | `Container::getComponent`/`createComponent`/`addComponent` |
