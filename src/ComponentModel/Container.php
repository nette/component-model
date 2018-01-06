<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

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

	/** @var IComponent|null */
	private $cloning;


	/********************* interface IContainer ****************d*g**/


	/**
	 * Adds the specified component to the IContainer.
	 * @param  string|int $name
	 * @param  string|int $insertBefore
	 * @return static
	 * @throws Nette\InvalidStateException
	 */
	public function addComponent(IComponent $component, $name, ?string $insertBefore = null)
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

		try {
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
			$component->setParent($this, $name);

		} catch (\Exception $e) {
			unset($this->components[$name]); // undo
			throw $e;
		}
		return $this;
	}


	/**
	 * Removes a component from the IContainer.
	 */
	public function removeComponent(IComponent $component): void
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
	 * @param  string|int
	 * @param  bool   throw exception if component doesn't exist?
	 */
	final public function getComponent($name, bool $throw = true): ?IComponent
	{
		if (isset($this->components[$name])) {
			return $this->components[$name];
		}

		if (is_int($name)) {
			$name = (string) $name;

		} elseif (!is_string($name)) {
			throw new Nette\InvalidArgumentException(sprintf('Component name must be integer or string, %s given.', gettype($name)));

		} else {
			$a = strpos($name, self::NAME_SEPARATOR);
			if ($a !== false) {
				$ext = substr($name, $a + 1);
				$name = substr($name, 0, $a);
			}

			if ($name === '') {
				if ($throw) {
					throw new Nette\InvalidArgumentException('Component or subcomponent name must not be empty string.');
				}
				return null;
			}
		}

		if (!isset($this->components[$name])) {
			$component = $this->createComponent($name);
			if ($component) {
				if (!$component instanceof IComponent) {
					throw new Nette\UnexpectedValueException('Method createComponent() did not return Nette\ComponentModel\IComponent.');

				} elseif (!isset($this->components[$name])) {
					$this->addComponent($component, $name);
				}
			}
		}

		if (isset($this->components[$name])) {
			if (!isset($ext)) {
				return $this->components[$name];

			} elseif ($this->components[$name] instanceof IContainer) {
				return $this->components[$name]->getComponent($ext, $throw);

			} elseif ($throw) {
				throw new Nette\InvalidArgumentException("Component with name '$name' is not container and cannot have '$ext' component.");
			}

		} elseif ($throw) {
			$hint = Nette\Utils\ObjectMixin::getSuggestion(array_merge(
				array_keys($this->components),
				array_map('lcfirst', preg_filter('#^createComponent([A-Z0-9].*)#', '$1', get_class_methods($this)))
			), $name);
			throw new Nette\InvalidArgumentException("Component with name '$name' does not exist" . ($hint ? ", did you mean '$hint'?" : '.'));
		}
		return null;
	}


	/**
	 * Component factory. Delegates the creation of components to a createComponent<Name> method.
	 */
	protected function createComponent(string $name): ?IComponent
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
		return null;
	}


	/**
	 * Iterates over components.
	 */
	final public function getComponents(bool $deep = false, string $filterType = null): \Iterator
	{
		$iterator = new RecursiveComponentIterator($this->components);
		if ($deep) {
			$iterator = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);
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
	 * @throws Nette\InvalidStateException
	 */
	protected function validateChildComponent(IComponent $child): void
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
	 * @internal
	 */
	final public function _isCloning(): ?IComponent
	{
		return $this->cloning;
	}
}
