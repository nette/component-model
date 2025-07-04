<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\ComponentModel;

use Nette;
use function func_num_args, in_array, substr;


/**
 * Base class for all components. Components have a parent, name, and can be monitored by ancestors.
 *
 * @template T of IContainer
 * @implements IComponent<T>
 * @property-read string $name
 * @property-read T|null $parent
 */
abstract class Component implements IComponent
{
	use Nette\SmartObject;

	private ?IContainer $parent = null;
	private ?string $name = null;

	/** @var array<string, array{?IComponent, ?int, ?string, array<int, array{?callable, ?callable}>}> means [type => [obj, depth, path, [attached, detached]]] */
	private array $monitors = [];


	/**
	 * Finds the closest ancestor of specified type.
	 * @param  bool  $throw   throw exception if component doesn't exist?
	 * @return ($throw is true ? IComponent : ?IComponent)
	 */
	final public function lookup(?string $type, bool $throw = true): ?IComponent
	{
		if (!isset($this->monitors[$type])) { // not monitored or not processed yet
			$obj = $this->parent;
			$path = self::NameSeparator . $this->name;
			$depth = 1;
			while ($obj !== null) {
				$parent = $obj->getParent();
				if ($type ? $obj instanceof $type : $parent === null) {
					break;
				}

				$path = self::NameSeparator . $obj->getName() . $path;
				$depth++;
				$obj = $parent; // IComponent::getParent()
				if ($obj === $this) {
					$obj = null; // prevent cycling
				}
			}

			if ($obj) {
				$this->monitors[$type] = [$obj, $depth, substr($path, 1), []];

			} else {
				$this->monitors[$type] = [null, null, null, []]; // not found
			}
		}

		if ($throw && $this->monitors[$type][0] === null) {
			$message = $this->name !== null
				? "Component '$this->name' is not attached to '$type'."
				: "Component of type '" . static::class . "' is not attached to '$type'.";
			throw new Nette\InvalidStateException($message);
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
		return $this->monitors[$type][2];
	}


	/**
	 * Starts monitoring ancestors for attach/detach events.
	 */
	final public function monitor(string $type, ?callable $attached = null, ?callable $detached = null): void
	{
		if (!$attached && !$detached) {
			throw new Nette\InvalidStateException('At least one handler is required.');
		}

		if (
			($obj = $this->lookup($type, throw: false))
			&& $attached
			&& !in_array([$attached, $detached], $this->monitors[$type][3], strict: true)
		) {
			$attached($obj);
		}

		$this->monitors[$type][3][] = [$attached, $detached]; // mark as monitored
	}


	/**
	 * Stops monitoring ancestors of specified type.
	 */
	final public function unmonitor(string $type): void
	{
		unset($this->monitors[$type]);
	}


	/********************* interface IComponent ****************d*g**/


	final public function getName(): ?string
	{
		return $this->name;
	}


	/**
	 * Returns the parent container if any.
	 * @return T
	 */
	final public function getParent(): ?IContainer
	{
		return $this->parent;
	}


	/**
	 * Sets or removes the parent of this component. This method is managed by containers and should
	 * not be called by applications
	 * @param  T  $parent
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

		// remove from parent?
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
	 * @param  T  $parent
	 * @throws Nette\InvalidStateException
	 */
	protected function validateParent(IContainer $parent): void
	{
	}


	/**
	 * Refreshes monitors.
	 * @param  array<string,true>|null  $missing  (array = attaching, null = detaching)
	 * @param  array<int,array{callable,IComponent}>  $listeners
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
			foreach ($this->monitors as $type => $rec) {
				if (isset($rec[1]) && $rec[1] > $depth) {
					if ($rec[3]) { // monitored
						$this->monitors[$type] = [null, null, null, $rec[3]];
						foreach ($rec[3] as $pair) {
							$listeners[] = [$pair[1], $rec[0]];
						}
					} else { // not monitored, just randomly cached
						unset($this->monitors[$type]);
					}
				}
			}
		} else { // attaching
			foreach ($this->monitors as $type => $rec) {
				if (isset($rec[0])) { // is in cache yet
					continue;

				} elseif (!$rec[3]) { // not monitored, just randomly cached
					unset($this->monitors[$type]);

				} elseif (isset($missing[$type])) { // known from previous lookup
					$this->monitors[$type] = [null, null, null, $rec[3]];

				} else {
					unset($this->monitors[$type]); // forces re-lookup
					if ($obj = $this->lookup($type, throw: false)) {
						foreach ($rec[3] as $pair) {
							$listeners[] = [$pair[0], $obj];
						}
					} else {
						$missing[$type] = true;
					}

					$this->monitors[$type][3] = $rec[3]; // mark as monitored
				}
			}
		}

		if ($depth === 0) { // call listeners
			$prev = [];
			foreach ($listeners as $item) {
				if ($item[0] && !in_array($item, $prev, strict: true)) {
					$item[0]($item[1]);
					$prev[] = $item;
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
	final public function __sleep()
	{
		throw new Nette\NotImplementedException('Object serialization is not supported by class ' . static::class);
	}


	/**
	 * Prevents unserialization.
	 */
	final public function __wakeup()
	{
		throw new Nette\NotImplementedException('Object unserialization is not supported by class ' . static::class);
	}
}
