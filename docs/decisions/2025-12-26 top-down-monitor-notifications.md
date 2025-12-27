# monitor() notifications fire top-down (ancestor → descendant)

Status: accepted · 2025-12-26 (commit `b4e82e1`) · BC break in 4.0

## Context

Callbacks registered via `monitor()` were historically invoked bottom-up on
attach: descendants first, then their ancestors. The old algorithm also worked
in two phases - it first walked the tree collecting the callbacks to run, and
only then fired them.

That had two flaws:

1. **Callbacks fired even for components no longer in the tree.** If an earlier
   callback (typically an ancestor's) had meanwhile removed a component from the
   tree, its already-collected callback still ran, referencing an ancestor the
   component was not actually attached to.
2. **An ancestor could not "prepare the ground" for its descendants.**
   Descendants were notified before the ancestor, so they could not rely on the
   ancestor having finished its own initialization against a shared ancestor
   (e.g. a Presenter).

## Decision

Notifications fire **top-down** (ancestor to descendant), and the algorithm
works **live**, without a collection phase. The implementation in
`Component::refreshMonitors()` explicitly handles tree mutation during listener
execution:

- a listener may modify the tree (remove itself, siblings, or ancestors);
- before processing a descendant, it is re-checked that it is still attached to
  the same parent (`$component->getParent() === $this`), so a removed descendant
  is not notified;
- deduplication: the same pair (callback + specific ancestor object) fires
  exactly once, even if the component moves during the operation;
- an `spl_object_id()` guard prevents re-entry and infinite loops.

## Detach order is the reverse (descendant → ancestor)

The decision above concerns **attach**. On **detach**, notifications fire the
other way, bottom-up: `refreshMonitors()` first descends into descendants and
only then processes its own `detached` callbacks. A subtree being detached is
torn down in the reverse order it was assembled.

This is deliberate and symmetric to attach. A callback always fires while the
tree above it is still intact: on attach a descendant is guaranteed the ancestor
has finished initializing; on detach it is guaranteed the ancestor is only about
to relinquish its own ancestor, so it can still rely on the whole path upward
during cleanup.

The behavior is pinned by tests: for a tree `A > B > C` where both `B` and `C`
monitor `A`, removing `B` always yields `detached(C)` first, then `detached(B)`.

## Consequences

- An ancestor is notified first and can "stop" a descendant: if it removes the
  descendant in its own callback, the descendant's callback never fires.
- The BC break is rare in practice. It only breaks code where two components
  coordinate through callbacks and rely on the old order, or code depending on
  the old bug (a callback firing after removal from the tree). A survey of
  libraries built on the component model found no real breakage.
- A typical `attached` callback only looks upward at its ancestor and handles
  itself; it does not depend on sibling/descendant order.

## Rejected alternatives

- **Keep bottom-up order and just add a check for removed components.** This
  would fix flaw #1, but an ancestor still could not influence its descendants'
  notifications or prepare their environment; the two-phase collection would
  remain a source of further edge cases.
- **Configurable order.** Needless API complexity for a scenario that does not
  occur in practice.
