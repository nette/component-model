# ComponentModel internals

Five small files, but a genuinely tricky core — the classic "small package,
sharp edges". This is one coherent mechanism (tree + monitoring + lookup +
cloning), so one file. The value is almost entirely in the monitor walks
(`Component::attachMonitors`/`detachMonitors`) and the cloning handshake; the rest
of the API is readable from its signatures.

## The lookup cache is dropped per subtree, apart from the monitor state

`lookup()` and `lookupPath()` cache their answer in **`$lookups`**, a pure cache
that never decides anything else. It stays right because the ancestors of a node
change only when a node on its path to the root changes its parent, and that node
then drops the cache of its whole subtree. Every change of the parent goes through
**`changeParent()`**, which does that, so no path can forget it:

- on attach the cache is dropped **after** the parent is set (the handlers then look
  up the final tree), on detach and on a rolled-back attach after it is cleared:
  the detach handlers run while the parent is still set, so what they look up must
  not outlive the removal;
- `__clone()` re-homes each cloned child through it, which drops the cache copied
  from the original, and a standalone clone clears its parent through it last, so
  even a lookup made by a `__clone()` override of a child, while the clone was
  still attached above, does not survive.

Renaming needs nothing: only a parentless component can be renamed, and the name
of a root is not part of any path below it. A newly created component drops only
its own (empty) subtree, so lazily created components do not cost the rest of the
tree its cache.

A component keeps three separate pieces of monitor state:

- **`$monitors`** — the registry, `type => [attachedHandlers, detachedHandlers]`.
  Only `monitor()` and `unmonitor()` change it; no walk ever touches it.
- **`$attachedDepths`** — `type => depth` of the ancestor the handlers of that type
  have been **notified about as attached**. It is the memory the detach walk needs,
  not a cache: it says who must be told `detached`. It holds a depth, not the
  object, so it stays right in a clone, whose ancestors are other objects at the
  same depths.
- **`$notifying`** — the types whose handlers are running right now (below).

The walks use the uncached `findAncestor()`. The root lookup is `lookup(null)`,
cached under the sentinel `self::RootKey`, which no class name can equal, and
selected by `$type === null`, never by the truthiness of the string: an empty or
unknown type is an ordinary type that never matches, so `monitor('')` is a dead
monitor like any typo.

## Top-down attach, bottom-up detach — the central invariant

`setParent()` drives two walks over the moved subtree, and their order encodes the
whole contract:

1. **`attachMonitors`** processes **this node's** monitors (fires `attached`) —
   *before* recursing into the children;
2. **`detachMonitors`** recurses into the children first and processes this node's
   monitors (fires `detached`) — *after* them.

Therefore **attach notifications fire ancestor→descendant (top-down)** and
**detach fires descendant→ancestor (bottom-up)** — symmetric, and emergent only
from that ordering. (The rationale is the promoted ADR
`docs/decisions/2025-12-26 top-down-monitor-notifications.md`; this file states the
*what*, the ADR the *why*.)

The state is always updated **before** the handlers run: `$attachedDepths[$type]`
is set before `attached` and removed before `detached`. Everything below relies on
that.

Properties of this live, single-pass algorithm that are the traps:

- **No collection phase — listeners may mutate the tree mid-walk.** The recursion
  guards against it: `$processed` (keyed by `spl_object_id`) prevents re-entry, and
  each child is re-checked with **`$component->getParent() === $this`** because a
  previous sibling's listener may already have removed it. The detach walk scans
  the children again until none is left unvisited, also after the container's own
  monitors, since a handler may add a child to the container being cut off, which
  must leave with it (one added by the container's own handler is detached after
  them, the only break of the bottom-up order). A handler may also cut off an
  ancestor; the nested walk then updates `$attachedDepths` of this very node,
  which is why the walk reads it live, never from a copy taken before the loop.
- **Callbacks are deduplicated by callback, ancestor, component and its `$moves`**
  (the `$called` accumulator), so the same handler fires **once per ancestor
  object** even if reached by multiple routes, but again once the component has
  moved and come back. For the same reason `$processed` remembers the `$moves` of
  each visited component, so a walk revisits one a handler has put back since.
- **Detach is depth-gated.** Only monitors notified about an ancestor **deeper than
  the detachment point** (`$attachedDepths[$type] > $depth`) fire `detached` — an
  ancestor inside the subtree that is cut off stays reachable and must **not**
  notify. The ancestor itself is found by climbing that many levels, while the
  parent is still set.
- **The handlers of a type are told about a change they make to their own component
  only once they return.** While they run, `notify()` mutes that type on that
  component, so a handler that removes and re-adds or moves its own component is not
  called again in the middle (and does not loop). The nested walk still updates
  `$attachedDepths`, so the state follows the tree. Once the handlers return,
  `notify()` compares where the component has ended up with what they were told
  (after `attached` always, since a handler may also have cut off an ancestor;
  after `detached` only when the component itself moved, since the ancestor being
  left is still there until the walk ends): if the ancestor differs, all of them
  get `detached` from the old one and `attached` from the new one, so the pairs
  match for every handler of the type, whichever of them moved it. The outer walk
  then stops processing the moved component, and `setParent()` does not finish a
  change a handler has superseded. A move is detected by **`$moves`**, a counter
  `changeParent()` increments, never by comparing the parent: a handler may remove
  the component and add it back to the very same container.
  `Container::addComponent`/`removeComponent` likewise touch the array only if the
  component is not attached there again.

`monitor()` deduplicates both handlers on registration, and fires a newly
registered `attached` **immediately** if the ancestor already exists.

A `detached` handler runs *before* the parent is cleared, so `lookup()` inside it
still finds the ancestor being left.

## A throwing listener vetoes the change

An exception from a listener is a veto, and `Form` relies on it: it forbids
nested forms by throwing from an `attached` handler, which `validateParent` could
not do, since the nesting may arise when an ancestor is attached later. So
`setParent()` rolls the change back by running **the opposite walk**: a failed
attach is detached again (parent and name restored), a failed detach is attached
again. The rollback reuses the ordinary walk with its depth gate, so it notifies
exactly the listeners the failed walk had reached, and it works on the live tree,
never a snapshot, so changes a listener legitimately made are kept. If a listener
has moved the component, before throwing or during the rollback, its move stands
and nothing more is rolled back.

A veto does not stop the other handlers of the same type: they are called too
and the exception goes on afterwards, so the rollback, which tells the whole type,
balances every one of them. It is thrown only after the type has been told where a
component its handler moved has ended up.

The rollback walk (`$rollingBack`) goes **to the end even past handlers that
throw**: `notify()` drops their exceptions and calls the next handler, so every
listener reached gets its counterpart. The veto is what gets thrown.

The cost is that the listener which threw also gets the opposite notification;
listeners are not paired, so this cannot be told apart.

**Where the guarantees end.** A randomized check of the invariants (tree and array
agree, `lookup()` matches the live tree, `$attachedDepths` matches the live
ancestor, every handler gets alternating `attached`/`detached` of the same
ancestor) holds for handlers that remove their component, its siblings or
ancestors, as the ADR on the notification order allows. A handler that vetoes a
rollback started by another veto, or that keeps moving components while a veto
is being rolled back, can still leave a handler unbalanced or a stale depth.

## Cloning: the container flags itself, children re-home to the clone

Cloning a subtree must re-parent every cloned child onto the cloned container, and
it is done with a cross-instance handshake rather than a parameter:

- **`Container::__clone`** takes the original container (`reset($components)->getParent()`),
  sets **`$original->cloning = $this`** (the new clone), clones each child, then
  clears the flag in a `finally`, so a throwing child `__clone` cannot leave it set.
  The flag is a **private property of `Component`**, because `Component::__clone`
  reads it on another instance; `Container` writes it through a closure bound to
  the `Component` scope. There is deliberately no method for it: any non-private
  method on `Component` would collide with methods of user components.
- **`Component::__clone`** of each child calls `changeParent($this->parent->cloning)`
  when the old parent is mid-clone, re-homing it to the **new** clone with its
  `$attachedDepths` intact (and dropping the cache copied from the original);
  otherwise (a standalone clone) it runs the detach walk while the parent is still
  set, then **detaches**.

So the direction of the wiring is: the parent announces "I am cloning, here is my
replacement," and each child, as PHP clones it, asks and redirects itself. The
`cloning` flag is only valid for the duration of the child-clone loop.

Components **cannot be serialized** — `__serialize` throws by design.

## `getComponents()` vs `getComponentTree()`, and why names can't be a flat map

- **`getComponents()` returns immediate children only.** Passing the old recursive
  or filter arguments throws `DeprecatedException` (checked via
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
  throws (a `validateParent` veto, a component that already has a parent, or a
  listener veto that `setParent` has already rolled back), unless a listener has
  put the component back under that very name. It also walks ancestors to reject
  circular references.
- **`removeComponent` mirrors it:** it calls `setParent(null)` first and only then
  drops the child from the array, provided the entry still holds that component
  and the component has not been added back (a listener may have removed it and
  put another one under its name, or put it back itself). So both
  `attached` and `detached` listeners run while the component is fully in the tree
  (parent set *and* listed), and a vetoed removal leaves the component in its
  place, with nothing to undo.

## Navigation map

| Concern | Where |
|---|---|
| Lookups and their cache | `Component::lookup`, `lookupPath`, `findAncestor`, `changeParent`/`forgetLookups` |
| Monitor state | `Component::$monitors`, `$attachedDepths`, `$notifying` |
| Attach/detach ordering & guards | `Component::attachMonitors`/`detachMonitors`/`notify` |
| Listener veto and rollback | `Component::setParent`/`notify` |
| Register listeners | `Component::monitor`/`unmonitor` |
| Clone re-homing handshake | `Container::__clone`, `Component::$cloning`/`__clone` |
| Immediate vs full tree | `Container::getComponents`/`getComponentTree` |
| Factory, path descent, add/remove safety | `Container::getComponent`/`createComponent`/`addComponent`/`removeComponent` |
