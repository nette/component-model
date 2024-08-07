<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\ComponentModel;

use Nette;


/**
 * Manages a collection of child components.
 *
 * @property-read IComponent[] $components
 */
class Container extends Component implements IContainer
{
	private const NameRegexp = '#^[a-zA-Z0-9_]+$#D';

	/** @var IComponent[] */
	private array $components = [];
	private ?Container $cloning = null;


	/********************* interface IContainer ****************d*g**/


	/**
	 * Adds a child component to the container.
	 * @return static
	 * @throws Nette\InvalidStateException
	 */
	public function addComponent(IComponent $component, ?string $name, ?string $insertBefore = null)
	{
		if ($name === null) {
			$name = $component->getName();
			if ($name === null) {
				throw new Nette\InvalidStateException("Missing component's name.");
			}
		}

		if (!preg_match(self::NameRegexp, $name)) {
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
				if ((string) $k === $insertBefore) {
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
		} catch (\Throwable $e) {
			unset($this->components[$name]); // undo
			throw $e;
		}

		return $this;
	}


	/**
	 * Removes a child component from the container.
	 */
	public function removeComponent(IComponent $component): void
	{
		$name = $component->getName();
		if (($this->components[$name] ?? null) !== $component) {
			throw new Nette\InvalidArgumentException("Component named '$name' is not located in this container.");
		}

		unset($this->components[$name]);
		$component->setParent(null);
	}


	/**
	 * Retrieves a child component by name or creates it if it doesn't exist.
	 * @param  bool  $throw  throw exception if component doesn't exist?
	 * @return ($throw is true ? IComponent : ?IComponent)
	 */
	final public function getComponent(string $name, bool $throw = true): ?IComponent
	{
		[$name] = $parts = explode(self::NameSeparator, $name, 2);

		if (!isset($this->components[$name])) {
			if (!preg_match(self::NameRegexp, $name)) {
				if ($throw) {
					throw new Nette\InvalidArgumentException("Component name must be non-empty alphanumeric string, '$name' given.");
				}

				return null;
			}

			$component = $this->createComponent($name);
			if ($component && !isset($this->components[$name])) {
				$this->addComponent($component, $name);
			}
		}

		$component = $this->components[$name] ?? null;
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
				array_map('strval', array_keys($this->components)),
				array_map('lcfirst', preg_filter('#^createComponent([A-Z0-9].*)#', '$1', get_class_methods($this))),
			), $name);
			throw new Nette\InvalidArgumentException("Component with name '$name' does not exist" . ($hint ? ", did you mean '$hint'?" : '.'));
		}

		return null;
	}


	/**
	 * Creates a new component. Delegates creation to createComponent<Name> method if it exists.
	 */
	protected function createComponent(string $name): ?IComponent
	{
		$ucname = ucfirst($name);
		$method = 'createComponent' . $ucname;
		if (
			$ucname !== $name
			&& method_exists($this, $method)
			&& (new \ReflectionMethod($this, $method))->getName() === $method
		) {
			$component = $this->$method($name);
			if (!$component instanceof IComponent && !isset($this->components[$name])) {
				$class = static::class;
				throw new Nette\UnexpectedValueException("Method $class::$method() did not return or create the desired component.");
			}

			return $component;
		}

		return null;
	}


	/**
	 * Returns all immediate child components.
	 * @return array<int|string,IComponent>
	 */
	final public function getComponents(): iterable
	{
		$filterType = func_get_args()[1] ?? null;
		if (func_get_args()[0] ?? null) { // back compatibility
			$iterator = new RecursiveComponentIterator($this->components);
			$iterator = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);
			if ($filterType) {
				$iterator = new \CallbackFilterIterator($iterator, fn($item) => $item instanceof $filterType);
			}
			return $iterator;
		}

		return $filterType
			? array_filter($this->components, fn($item) => $item instanceof $filterType)
			: $this->components;
	}


	/**
	 * Retrieves the entire hierarchy of components, including all nested child components (depth-first).
	 * @return list<IComponent>
	 */
	final public function getComponentTree(): array
	{
		$res = [];
		foreach ($this->components as $component) {
			$res[] = $component;
			if ($component instanceof self) {
				$res = array_merge($res, $component->getComponentTree());
			}
		}
		return $res;
	}


	/**
	 * Validates a child component before it's added to the container.
	 * Descendant classes can override this to implement custom validation logic.
	 * @throws Nette\InvalidStateException
	 */
	protected function validateChildComponent(IComponent $child): void
	{
	}


	/********************* cloneable, serializable ****************d*g**/


	/**
	 * Handles object cloning. Clones all child components and re-sets their parents.
	 */
	public function __clone()
	{
		if ($this->components) {
			$oldMyself = reset($this->components)->getParent();
			assert($oldMyself instanceof self);
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
	final public function _isCloning(): ?self
	{
		return $this->cloning;
	}
}
