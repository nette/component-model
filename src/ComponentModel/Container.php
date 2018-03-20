<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\ComponentModel;

use Nette;


/**
 * ComponentContainer is default implementation of IContainer.
 *
 * @property-read \Iterator $components
 */
class Container extends Component implements IContainer
{
	/** @var IComponent[] */
	private $components = [];

	/** @var Container|null */
	private $cloning;


	/********************* interface IContainer ****************d*g**/


	/**
	 * Adds the component to the container.
	 * @param  string|int|null  $name
	 * @param  string|int  $insertBefore
	 * @return static
	 * @throws Nette\InvalidStateException
	 */
	public function addComponent(IComponent $component, $name, $insertBefore = null)
	{
		if ($name === null) {
			$name = $component->getName();
		}

		if (is_int($name)) {
			$name = (string) $name;

		} elseif (!is_string($name)) {
			throw new Nette\InvalidArgumentException(sprintf('Component name must be integer or string, %s given.', gettype($name)));

		} elseif (!preg_match('#^[a-zA-Z0-9_]+\z#', $name)) {
			throw new Nette\InvalidArgumentException("Component name must be non-empty alphanumeric string, '$name' given.");
		}

		if (isset($this->components[$name])) {
			throw new Nette\InvalidStateException("Component with name '$name' already exists.");
		}

		// check circular reference
		$obj = $this;
		do {
			if ($obj === $component) {
				throw new Nette\InvalidStateException("Circular reference detected while adding component '$name'.");
			}
			$obj = $obj->getParent();
		} while ($obj !== null);

		// user checking
		$this->validateChildComponent($component);

		if (isset($this->components[$insertBefore])) {
			$tmp = [];
			foreach ($this->components as $k => $v) {
				if ($k === $insertBefore) {
					$tmp[$name] = $component;
				}
				$tmp[$k] = $v;
			}
			$this->components = $tmp;
		} else {
			$this->components[$name] = $component;
		}

		try {
			$component->setParent($this, $name);
		} catch (\Exception $e) {
			unset($this->components[$name]); // undo
			throw $e;
		}
		return $this;
	}


	/**
	 * Removes the component from the container.
	 * @return void
	 */
	public function removeComponent(IComponent $component)
	{
		$name = $component->getName();
		if (!isset($this->components[$name]) || $this->components[$name] !== $component) {
			throw new Nette\InvalidArgumentException("Component named '$name' is not located in this container.");
		}

		unset($this->components[$name]);
		$component->setParent(null);
	}


	/**
	 * Returns component specified by name or path.
	 * @param  string|int  $name
	 * @param  bool  $throw  throw exception if component doesn't exist?
	 * @return IComponent|null
	 */
	public function getComponent($name, $throw = true)
	{
		if (!is_int($name) && !is_string($name)) {
			throw new Nette\InvalidArgumentException(sprintf('Component name must be integer or string, %s given.', gettype($name)));
		}

		list($name) = $parts = explode(self::NAME_SEPARATOR, (string) $name, 2);

		if (!isset($this->components[$name])) {
			if (!preg_match('#^[a-zA-Z0-9_]+\z#', $name)) {
				if ($throw) {
					throw new Nette\InvalidArgumentException("Component name must be non-empty alphanumeric string, '$name' given.");
				}
				return;
			}

			$component = $this->createComponent($name);
			if ($component) {
				if (!$component instanceof IComponent) {
					throw new Nette\UnexpectedValueException('Method createComponent() did not return Nette\ComponentModel\IComponent.');

				} elseif (!isset($this->components[$name])) {
					$this->addComponent($component, $name);
				}
			}
		}

		$component = isset($this->components[$name]) ? $this->components[$name] : null;
		if ($component !== null) {
			if (!isset($parts[1])) {
				return $component;

			} elseif ($component instanceof IContainer) {
				return $component->getComponent($parts[1], $throw);

			} elseif ($throw) {
				throw new Nette\InvalidArgumentException("Component with name '$name' is not container and cannot have '$parts[1]' component.");
			}

		} elseif ($throw) {
			$hint = Nette\Utils\ObjectHelpers::getSuggestion(array_merge(
				array_keys($this->components),
				array_map('lcfirst', preg_filter('#^createComponent([A-Z0-9].*)#', '$1', get_class_methods($this)))
			), $name);
			throw new Nette\InvalidArgumentException("Component with name '$name' does not exist" . ($hint ? ", did you mean '$hint'?" : '.'));
		}
	}


	/**
	 * Component factory. Delegates the creation of components to a createComponent<Name> method.
	 * @param  string  $name
	 * @return IComponent|null
	 */
	protected function createComponent($name)
	{
		$ucname = ucfirst($name);
		$method = 'createComponent' . $ucname;
		if ($ucname !== $name && method_exists($this, $method) && (new \ReflectionMethod($this, $method))->getName() === $method) {
			$component = $this->$method($name);
			if (!$component instanceof IComponent && !isset($this->components[$name])) {
				$class = get_class($this);
				throw new Nette\UnexpectedValueException("Method $class::$method() did not return or create the desired component.");
			}
			return $component;
		}
	}


	/**
	 * Iterates over descendants components.
	 * @param  bool  $deep  return all descendant?
	 * @param  string  $filterType  class type to return
	 * @return \Iterator
	 */
	public function getComponents($deep = false, $filterType = null)
	{
		$iterator = new RecursiveComponentIterator($this->components);
		if ($deep) {
			$deep = $deep > 0 ? \RecursiveIteratorIterator::SELF_FIRST : \RecursiveIteratorIterator::CHILD_FIRST;
			$iterator = new \RecursiveIteratorIterator($iterator, $deep);
		}
		if ($filterType) {
			$iterator = new \CallbackFilterIterator($iterator, function ($item) use ($filterType) {
				return $item instanceof $filterType;
			});
		}
		return $iterator;
	}


	/**
	 * Descendant can override this method to disallow insert a child by throwing an Nette\InvalidStateException.
	 * @return void
	 * @throws Nette\InvalidStateException
	 */
	protected function validateChildComponent(IComponent $child)
	{
	}


	/********************* cloneable, serializable ****************d*g**/


	/**
	 * Object cloning.
	 */
	public function __clone()
	{
		if ($this->components) {
			$oldMyself = reset($this->components)->getParent();
			$oldMyself->cloning = $this;
			foreach ($this->components as $name => $component) {
				$this->components[$name] = clone $component;
			}
			$oldMyself->cloning = null;
		}
		parent::__clone();
	}


	/**
	 * Is container cloning now?
	 * @return Container|null
	 * @internal
	 */
	public function _isCloning()
	{
		return $this->cloning;
	}
}
