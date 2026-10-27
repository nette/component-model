<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\ComponentModel;

use Nette;


/**
 * Base class for all components. Components have a parent, name, and can be monitored by ancestors.
 */
abstract class Component implements IComponent
{
	private const RootKey = "\0root";

	private ?IContainer $parent = null;
	private ?string $name = null;

	/** Counts the changes of the parent, so that a walk can tell that a handler has moved the component meanwhile. */
	private int $moves = 0;

	/** The clone this component is being cloned into; set only while Container::__clone() runs. */
	private ?Container $cloning = null;

	/**
	 * Attached and detached handlers by monitored type.
	 * @var array<class-string<IComponent>, array{list<\Closure(IComponent): void>, list<\Closure(IComponent): void>}>
	 */
	private array $monitors = [];

	/**
	 * Depth of the ancestor the handlers of a monitored type have been notified about as attached.
	 * @var array<class-string<IComponent>, int>
	 */
	private array $attachedDepths = [];

	/** @var array<class-string<IComponent>, true>  monitored types whose handlers are running */
	private array $notifying = [];

	/**
	 * Cached ancestor and path by looked-up type, dropped whenever an ancestor changes.
	 * @var array<self::RootKey|class-string<IComponent>, array{?IComponent, ?string}>
	 */
	private array $lookups = [];


	/**
	 * Finds the closest ancestor of specified type, or the root when $type is null.
	 * @template T of IComponent
	 * @param ?class-string<T>  $type
	 * @param bool  $throw  throw exception if component doesn't exist?
	 * @return ($type is null ? ($throw is true ? IComponent : ?IComponent) : ($throw is true ? T : ?T))
	 */
	final public function lookup(?string $type, bool $throw = true): ?IComponent
	{
		$entry = $this->lookups[$type ?? self::RootKey] ??= [$this->findAncestor($type)[0] ?? null, null];
		if ($throw && $entry[0] === null) {
			$desc = $this->name === null ? "type of '" . static::class . "'" : "'$this->name'";
			throw new Nette\InvalidStateException("Component $desc is not attached to '$type'.");
		}

		return $entry[0];
	}


	/**
	 * Finds the closest ancestor specified by class or interface name and returns backtrace path.
	 * A path is the concatenation of component names separated by self::NameSeparator.
	 * @param ?class-string<IComponent>  $type
	 * @param bool  $throw  throw exception if component doesn't exist?
	 * @return ($throw is true ? string : ?string)
	 */
	final public function lookupPath(?string $type = null, bool $throw = true): ?string
	{
		$ancestor = $this->lookup($type, $throw);
		if ($ancestor === null) {
			return null;
		}

		$key = $type ?? self::RootKey;
		if (($path = $this->lookups[$key][1]) === null) {
			$path = $this->name ?? '';
			for ($obj = $this->parent; $obj !== null && $obj !== $ancestor; $obj = $obj->getParent()) {
				$path = $obj->getName() . self::NameSeparator . $path;
			}

			$this->lookups[$key][1] = $path;
		}

		return $path;
	}


	/**
	 * Finds the closest ancestor of specified type, or the root when $type is null, and its depth.
	 * @template T of IComponent
	 * @param ?class-string<T>  $type
	 * @return ($type is null ? ?array{IComponent, int} : ?array{T, int})
	 */
	private function findAncestor(?string $type): ?array
	{
		$depth = 1;
		for ($ancestor = $this->parent; $ancestor !== null && $ancestor !== $this; $ancestor = $parent) { // $this prevents cycling
			$parent = $ancestor->getParent();
			if ($type === null ? $parent === null : $ancestor instanceof $type) {
				return [$ancestor, $depth];
			}

			$depth++;
		}

		return null;
	}


	/**
	 * Returns the ancestor $depth levels up.
	 */
	private function getAncestor(int $depth): IComponent
	{
		$ancestor = $this;
		while ($depth--) {
			$ancestor = $ancestor->getParent() ?? throw new Nette\ShouldNotHappenException;
		}

		return $ancestor;
	}


	/**
	 * Drops the cached lookups of this subtree, whose ancestors have changed.
	 */
	private function forgetLookups(): void
	{
		$this->lookups = [];
		if ($this instanceof IContainer) {
			foreach ($this->getComponents() as $component) {
				if ($component instanceof self) {
					$component->forgetLookups();
				}
			}
		}
	}


	/**
	 * Starts monitoring ancestors for attach/detach events.
	 * @template T of IComponent
	 * @param class-string<T>  $type
	 * @param ?(callable(T): void)  $attached  called when attached to a monitored ancestor
	 * @param ?(callable(T): void)  $detached  called before detaching from a monitored ancestor
	 */
	final public function monitor(string $type, ?callable $attached = null, ?callable $detached = null): void
	{
		if (!$attached && !$detached) {
			throw new Nette\InvalidStateException('At least one handler is required.');
		}

		$found = $this->findAncestor($type);
		$this->monitors[$type] ??= [[], []];
		if ($found) {
			$this->attachedDepths[$type] = $found[1];
		}

		if ($attached && !in_array($attached(...), $this->monitors[$type][0])) {
			$this->monitors[$type][0][] = $attached(...);
			if ($found) {
				$attached($found[0]);
			}
		}

		if ($detached && !in_array($detached(...), $this->monitors[$type][1])) {
			$this->monitors[$type][1][] = $detached(...);
		}
	}


	/**
	 * Stops monitoring ancestors of specified type.
	 * @param class-string<IComponent>  $type
	 */
	final public function unmonitor(string $type): void
	{
		unset($this->monitors[$type], $this->attachedDepths[$type]);
	}


	/********************* interface IComponent ****************d*g**/


	final public function getName(): ?string
	{
		return $this->name;
	}


	/**
	 * Returns the parent container if any.
	 */
	final public function getParent(): ?IContainer
	{
		return $this->parent;
	}


	/**
	 * Sets or removes the parent of this component. This method is managed by containers and should
	 * not be called by applications
	 * @throws Nette\InvalidStateException
	 * @internal
	 */
	public function setParent(?IContainer $parent, ?string $name = null): static
	{
		if ($parent === $this->parent) { // parent unchanged
			if ($name !== null && $parent !== null) {
				throw new Nette\InvalidStateException("Component '$this->name' already has a parent.");
			} elseif ($name !== null) {
				$this->name = $name; // just rename
			}

			return $this;
		}

		// A component cannot be given a parent if it already has a parent.
		if ($this->parent !== null && $parent !== null) {
			throw new Nette\InvalidStateException("Component '$this->name' already has a parent.");
		}

		// a listener that throws vetoes the change, which is rolled back by the opposite walk,
		// unless a listener has moved the component meanwhile
		if ($parent === null) { // remove from parent
			$moves = $this->moves;
			try {
				$this->detachMonitors(0);
			} catch (\Throwable $e) {
				if ($this->moves === $moves) {
					$this->attachMonitors(rollingBack: true);
				}

				throw $e;
			}

			if ($this->moves === $moves) {
				$this->changeParent(null);
			}

		} else { // add to parent
			$this->validateParent($parent);
			$oldName = $this->name;
			$this->name = $name ?? $oldName;
			$this->changeParent($parent);
			$moves = $this->moves;
			try {
				$this->attachMonitors();
			} catch (\Throwable $e) {
				if ($this->moves === $moves) {
					$this->detachMonitors(0, rollingBack: true);
					if ($this->moves === $moves) { // a handler of the rollback may have moved it too
						$this->name = $oldName;
						$this->changeParent(null);
					}
				}

				throw $e;
			}
		}

		return $this;
	}


	/**
	 * Sets the parent and drops the cached lookups below, whose ancestors have changed.
	 */
	private function changeParent(?IContainer $parent): void
	{
		$this->parent = $parent;
		$this->moves++;
		$this->forgetLookups();
	}


	/**
	 * Validates the new parent before it's set.
	 * Descendant classes can override this to implement custom validation logic.
	 * @throws Nette\InvalidStateException
	 */
	protected function validateParent(IContainer $parent): void
	{
	}


	/**
	 * Notifies the monitors of this subtree, which has just been attached, about the ancestors they have found.
	 * A rollback goes on past handlers that throw.
	 * @param  list<array{\Closure(IComponent): void, int, int, int}>  $called  deduplication tracking
	 * @param  array<int, int>  $processed  prevents reentry, by the moves of each processed component
	 */
	private function attachMonitors(bool $rollingBack = false, array &$called = [], array &$processed = []): void
	{
		$processed[spl_object_id($this)] = $this->moves;
		$moves = $this->moves;

		foreach (array_keys($this->monitors) as $type) {
			if (!isset($this->attachedDepths[$type]) // not notified yet, the ancestor is not inside the subtree
				&& ($found = $this->findAncestor($type))
			) {
				$this->attachedDepths[$type] = $found[1];
				$this->notify($type, 0, $found[0], $rollingBack, $called);
				if ($this->moves !== $moves) {
					return; // moved by a handler, the nested change has updated it
				}
			}
		}

		if ($this instanceof IContainer) {
			foreach ($this->getComponents() as $component) {
				if ($component instanceof self
					&& ($processed[spl_object_id($component)] ?? null) !== $component->moves // not processed yet, or moved back since
					&& $component->getParent() === $this  // may have been removed by previous sibling's listener
				) {
					$component->attachMonitors($rollingBack, $called, $processed);
					if ($this->moves !== $moves) {
						return;
					}
				}
			}
		}
	}


	/**
	 * Notifies the monitors of this subtree, which is about to be cut off $depth levels above, about the ancestors they lose.
	 * A rollback goes on past handlers that throw.
	 * @param  list<array{\Closure(IComponent): void, int, int, int}>  $called  deduplication tracking
	 * @param  array<int, int>  $processed  prevents reentry, by the moves of each processed component
	 */
	private function detachMonitors(
		int $depth,
		bool $rollingBack = false,
		array &$called = [],
		array &$processed = [],
	): void
	{
		$processed[spl_object_id($this)] = $this->moves;
		$moves = $this->moves;
		$own = false;

		do { // the children again until none is new, as a handler may have added one, which leaves with the rest
			$more = false;
			if ($this instanceof IContainer) {
				foreach ($this->getComponents() as $component) {
					if ($component instanceof self
						&& ($processed[spl_object_id($component)] ?? null) !== $component->moves // not processed yet, or moved back since
						&& $component->getParent() === $this  // may have been removed by previous sibling's listener
					) {
						$component->detachMonitors($depth + 1, $rollingBack, $called, $processed);
						if ($this->moves !== $moves) {
							return;
						}

						$more = true;
					}
				}
			}

			if (!$more && !$own) { // own monitors after the children, then the children once more
				$own = $more = true;
				$types = array_keys($this->attachedDepths);
				foreach ($types as $type) {
					$ancestorDepth = $this->attachedDepths[$type] ?? 0; // a previous handler may have changed it
					if ($ancestorDepth > $depth) { // the ancestor lies beyond the cut, an ancestor inside the subtree stays
						unset($this->attachedDepths[$type]);
						$this->notify($type, 1, $this->getAncestor($ancestorDepth), $rollingBack, $called);
						if ($this->moves !== $moves) {
							return; // moved by a handler, the nested change has updated it
						}
					}
				}
			}
		} while ($more);
	}


	/**
	 * Calls the attached (0) or detached (1) handlers of $type, unless they are running already: a change
	 * they make to their own component is not reported to them while they run, but once they return.
	 * @param  class-string<IComponent>  $type
	 * @param  0|1  $kind
	 * @param  list<array{\Closure(IComponent): void, int, int, int}>  $called
	 */
	private function notify(string $type, int $kind, IComponent $ancestor, bool $rollingBack, array &$called): void
	{
		if (isset($this->notifying[$type])) {
			return;
		}

		$moves = $this->moves;
		$failure = null;
		$this->notifying[$type] = true;
		foreach ($this->monitors[$type][$kind] ?? [] as $handler) {
			if (!in_array($key = [$handler, spl_object_id($ancestor), spl_object_id($this), $this->moves], $called)) { // deduplicate: same callback + same object = call once, until the component moves
				try {
					$handler($ancestor);
				} catch (\Throwable $e) {
					if (!$rollingBack) { // the others of the type are called too, so the rollback balances all of them
						$failure ??= $e;
					}
				}

				$called[] = $key;
			}
		}

		unset($this->notifying[$type]);

		// a handler may have moved the component or cut off an ancestor, the nested change has updated the state;
		// while detaching, the ancestor is still there until the walk ends, so only a move of its own counts
		if ($kind === 0 || $this->moves !== $moves) {
			$told = $kind === 0 ? $ancestor : null;
			$found = $this->findAncestor($type);
			if (($found[0] ?? null) !== $told) {
				$reconciled = [];
				if ($told) {
					$this->notify($type, 1, $told, $rollingBack || $failure, $reconciled);
				}

				if ($found) {
					$this->notify($type, 0, $found[0], $rollingBack || $failure, $reconciled);
				}
			}
		}

		if ($failure) {
			throw $failure;
		}
	}


	/********************* cloneable, serializable ****************d*g**/


	/**
	 * Object cloning.
	 */
	public function __clone()
	{
		$this->notifying = [];
		if ($this->parent instanceof Container && $this->parent->cloning) {
			$this->changeParent($this->parent->cloning); // reattach to the clone of the parent

		} elseif ($this->parent !== null) { // a standalone clone leaves the tree
			$this->detachMonitors(0);
			$this->changeParent(null);
		}
	}


	/**
	 * Prevents serialization.
	 */
	final public function __serialize()
	{
		throw new Nette\NotImplementedException('Object serialization is not supported by class ' . static::class);
	}
}
