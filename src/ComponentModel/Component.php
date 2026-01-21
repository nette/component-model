<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\ComponentModel;

use Nette;
use function func_num_args, in_array, substr;


/**
 * Base class for all components. Components have a parent, name, and can be monitored by ancestors.
 *
 * @property-read string $name
 * @property-read IContainer|null $parent
 */
abstract class Component implements IComponent
{
	use Nette\SmartObject;

	private ?IContainer $parent = null;
	private ?string $name = null;

	/**
	 * Monitors: tracks monitored ancestors and registered callbacks.
	 * Combines cached lookup results with callback registrations for each monitored type.
	 * Depth is used to detect when monitored ancestor becomes unreachable during detachment.
	 * Structure: [type => [found object, depth to object, path to object, [[attached, ...], [detached, ...]]]]
	 * @var array<string, array{?IComponent, ?int, ?string, array<int, ?array{\Closure[], \Closure[]}>}>
	 */
	private array $monitors = [];


	/**
	 * Finds the closest ancestor of specified type.
	 * @param  bool  $throw   throw exception if component doesn't exist?
	 * @return ($throw is true ? IComponent : ?IComponent)
	 */
	final public function lookup(?string $type, bool $throw = true): ?IComponent
	{
		$type ??= '';
		if (!isset($this->monitors[$type])) { // not monitored or not processed yet
			$ancestor = $this->parent;
			$path = self::NameSeparator . $this->name;
			$depth = 1;
			while ($ancestor !== null) {
				$parent = $ancestor->getParent();
				if ($type ? $ancestor instanceof $type : $parent === null) {
					break;
				}

				$path = self::NameSeparator . $ancestor->getName() . $path;
				$depth++;
				$ancestor = $parent; // IComponent::getParent()
				if ($ancestor === $this) {
					$ancestor = null; // prevent cycling
				}
			}

			$this->monitors[$type] = $ancestor
				? [$ancestor, $depth, substr($path, 1), null]
				: [null, null, null, null]; // not found
		}

		if ($throw && $this->monitors[$type][0] === null) {
			$desc = $this->name === null ? "type of '" . static::class . "'" : "'$this->name'";
			throw new Nette\InvalidStateException("Component $desc is not attached to '$type'.");
		}

		return $this->monitors[$type][0];
	}


	/**
	 * Finds the closest ancestor specified by class or interface name and returns backtrace path.
	 * A path is the concatenation of component names separated by self::NAME_SEPARATOR.
	 * @return ($throw is true ? string : ?string)
	 */
	final public function lookupPath(?string $type = null, bool $throw = true): ?string
	{
		$this->lookup($type, $throw);
		return $this->monitors[$type ?? ''][2];
	}


	/**
	 * Starts monitoring ancestors for attach/detach events.
	 */
	final public function monitor(string $type, ?callable $attached = null, ?callable $detached = null): void
	{
		if (func_num_args() === 1) {
			$attached = $this->attached(...);
			$detached = $this->detached(...);
		}

		$ancestor = $this->lookup($type, throw: false);
		$this->monitors[$type][3] ??= [[], []];

		if ($attached && !in_array($attached(...), $this->monitors[$type][3][0], strict: false)) {
			$this->monitors[$type][3][0][] = $attached(...);
			if ($ancestor) {
				$attached($ancestor);
			}
		}

		if ($detached) {
			$this->monitors[$type][3][1][] = $detached(...);
		}
	}


	/**
	 * Stops monitoring ancestors of specified type.
	 */
	final public function unmonitor(string $type): void
	{
		unset($this->monitors[$type]);
	}


	/**
	 * This method will be called when the component (or component's parent)
	 * becomes attached to a monitored object. Do not call this method yourself.
	 * @deprecated  use monitor($type, $attached)
	 */
	protected function attached(IComponent $obj): void
	{
	}


	/**
	 * This method will be called before the component (or component's parent)
	 * becomes detached from a monitored object. Do not call this method yourself.
	 * @deprecated  use monitor($type, null, $detached)
	 */
	protected function detached(IComponent $obj): void
	{
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
		if ($parent === null && $this->parent === null && $name !== null) {
			$this->name = $name; // just rename
			return $this;

		} elseif ($parent === $this->parent && $name === null) {
			return $this; // nothing to do
		}

		// A component cannot be given a parent if it already has a parent.
		if ($this->parent !== null && $parent !== null) {
			throw new Nette\InvalidStateException("Component '$this->name' already has a parent.");
		}

		// remove from parent
		if ($parent === null) {
			$this->refreshMonitors(0);
			$this->parent = null;

		} else { // add to parent
			$this->validateParent($parent);
			$this->parent = $parent;
			if ($name !== null) {
				$this->name = $name;
			}

			$tmp = [];
			$this->refreshMonitors(0, $tmp);
		}

		return $this;
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
	 * Refreshes monitors when attaching/detaching from component tree.
	 * @param  ?array<string, true>  $missing  null = detaching, array = attaching
	 * @param  array<int, array{\Closure, IComponent}>  $listeners
	 */
	private function refreshMonitors(int $depth, ?array &$missing = null, array &$listeners = []): void
	{
		if ($this instanceof IContainer) {
			foreach ($this->getComponents() as $component) {
				if ($component instanceof self) {
					$component->refreshMonitors($depth + 1, $missing, $listeners);
				}
			}
		}

		if ($missing === null) { // detaching
			foreach ($this->monitors as $type => [$ancestor, $inDepth, , $callbacks]) {
				if (isset($inDepth) && $inDepth > $depth) { // only process if ancestor was deeper than current detachment point
					if ($callbacks) {
						$this->monitors[$type] = [null, null, null, $callbacks]; // clear cached object, keep listener registrations
						foreach ($callbacks[1] as $detached) {
							$listeners[] = [$detached, $ancestor];
						}
					} else { // no listeners, just cached lookup result - clear it
						unset($this->monitors[$type]);
					}
				}
			}
		} else { // attaching
			foreach ($this->monitors as $type => [$ancestor, , , $callbacks]) {
				if (isset($ancestor)) { // already cached and valid - skip
					continue;

				} elseif (!$callbacks) { // no listeners, just old cached lookup - clear it
					unset($this->monitors[$type]);

				} elseif (isset($missing[$type])) { // already checked during this attach operation - ancestor not found
					$this->monitors[$type] = [null, null, null, $callbacks]; // keep listener registrations but clear cache

				} else { // need to check if ancestor exists
					unset($this->monitors[$type]); // force fresh lookup
					if ($ancestor = $this->lookup($type, throw: false)) {
						foreach ($callbacks[0] as $attached) {
							$listeners[] = [$attached, $ancestor];
						}
					} else {
						$missing[$type] = true; // ancestor not found - remember so we don't check again
					}

					$this->monitors[$type][3] = $callbacks; // restore listener (lookup() cached result in $this->monitors[$type])
				}
			}
		}

		if ($depth === 0) { // call listeners
			$called = [];
			foreach ($listeners as [$callback, $component]) {
				if (!in_array($key = [$callback, spl_object_id($component)], $called, strict: false)) { // deduplicate: same callback + same object = call once
					$callback($component);
					$called[] = $key;
				}
			}
		}
	}


	/********************* cloneable, serializable ****************d*g**/


	/**
	 * Object cloning.
	 */
	public function __clone()
	{
		if ($this->parent === null) {
			return;

		} elseif ($this->parent instanceof Container) {
			$this->parent = $this->parent->_isCloning();
			if ($this->parent === null) { // not cloning
				$this->refreshMonitors(0);
			}
		} else {
			$this->parent = null;
			$this->refreshMonitors(0);
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
