<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\ComponentModel;

use Nette;


/**
 * Component is the base class for all components.
 *
 * Components are objects implementing IComponent. They has parent component and own name.
 *
 * @property-read string $name
 * @property-read IContainer|null $parent
 */
abstract class Component implements IComponent
{
	use Nette\SmartObject;

	/** @var IContainer|null */
	private $parent;

	/** @var string|null */
	private $name;

	/** @var array of [type => [obj, depth, path, array of [attached, detached]]] */
	private $monitors = [];


	public function __construct()
	{
		list($parent, $name) = func_get_args() + [null, null];
		if ($parent !== null) {
			trigger_error(__METHOD__ . '() argument $parent is deprecated, use $parent->addComponent() instead.', E_USER_DEPRECATED);
			$parent->addComponent($this, $name);

		} elseif ($name !== null) {
			trigger_error(__METHOD__ . '() argument $name is deprecated, use $parent->setParent(null, $name) instead.', E_USER_DEPRECATED);
			$this->name = $name;
		}
	}


	/**
	 * Finds the closest ancestor specified by class or interface name.
	 * @param  string|null  $type
	 * @param  bool  $throw
	 * @return IComponent|null
	 */
	public function lookup($type, $throw = true)
	{
		if (!isset($this->monitors[$type])) { // not monitored or not processed yet
			$obj = $this->parent;
			$path = self::NAME_SEPARATOR . $this->name;
			$depth = 1;
			while ($obj !== null) {
				$parent = $obj->getParent();
				if ($type ? $obj instanceof $type : $parent === null) {
					break;
				}
				$path = self::NAME_SEPARATOR . $obj->getName() . $path;
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
			throw new Nette\InvalidStateException("Component '$this->name' is not attached to '$type'.");
		}

		return $this->monitors[$type][0];
	}


	/**
	 * Finds the closest ancestor specified by class or interface name and returns backtrace path.
	 * A path is the concatenation of component names separated by self::NAME_SEPARATOR.
	 * @param  string|null  $type
	 * @param  bool  $throw
	 * @return string|null
	 */
	public function lookupPath($type = null, $throw = true)
	{
		$this->lookup($type, $throw);
		return $this->monitors[$type][2];
	}


	/**
	 * Starts monitoring of ancestors.
	 * @param  string  $type
	 * @return void
	 */
	final public function monitor($type, callable $attached = null, callable $detached = null)
	{
		if (func_num_args() === 1) {
			$attached = [$this, 'attached'];
			$detached = [$this, 'detached'];
		}
		if (($obj = $this->lookup($type, false)) && $attached && !in_array([$attached, $detached], $this->monitors[$type][3], true)) {
			$attached($obj);
		}
		$this->monitors[$type][3][] = [$attached, $detached]; // mark as monitored
	}


	/**
	 * Stops monitoring of ancestors.
	 * @param  string  $type
	 * @return void
	 */
	public function unmonitor($type)
	{
		unset($this->monitors[$type]);
	}


	/**
	 * This method will be called when the component (or component's parent)
	 * becomes attached to a monitored object. Do not call this method yourself.
	 * @param  IComponent  $obj
	 * @return void
	 * @deprecated  use monitor($type, $attached)
	 */
	protected function attached($obj)
	{
	}


	/**
	 * This method will be called before the component (or component's parent)
	 * becomes detached from a monitored object. Do not call this method yourself.
	 * @param  IComponent  $obj
	 * @return void
	 * @deprecated  use monitor($type, null, $detached)
	 */
	protected function detached($obj)
	{
	}


	/********************* interface IComponent ****************d*g**/


	/**
	 * @return string|null
	 */
	public function getName()
	{
		return $this->name;
	}


	/**
	 * Returns the parent container if any.
	 * @return IContainer|null
	 */
	public function getParent()
	{
		return $this->parent;
	}


	/**
	 * Sets or removes the parent of this component. This method is managed by containers and should
	 * not be called by applications
	 * @param  string  $name
	 * @return static
	 * @throws Nette\InvalidStateException
	 * @internal
	 */
	public function setParent(IContainer $parent = null, $name = null)
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
	 * Is called by a component when it is about to be set new parent. Descendant can
	 * override this method to disallow a parent change by throwing an Nette\InvalidStateException
	 * @return void
	 * @throws Nette\InvalidStateException
	 */
	protected function validateParent(IContainer $parent)
	{
	}


	/**
	 * Refreshes monitors.
	 * @param  int  $depth
	 * @param  array|null  $missing (array = attaching, null = detaching)
	 * @param  array  $listeners
	 * @return void
	 */
	private function refreshMonitors($depth, &$missing = null, &$listeners = [])
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
					$this->monitors[$type] = null; // forces re-lookup
					if ($obj = $this->lookup($type, false)) {
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
				if ($item[0] && !in_array($item, $prev, true)) {
					call_user_func($item[0], $item[1]);
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
	public function __sleep()
	{
		throw new Nette\NotImplementedException('Object serialization is not supported by class ' . get_class($this));
	}


	/**
	 * Prevents unserialization.
	 */
	public function __wakeup()
	{
		throw new Nette\NotImplementedException('Object unserialization is not supported by class ' . get_class($this));
	}
}
