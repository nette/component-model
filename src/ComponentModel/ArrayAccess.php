<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\ComponentModel;

use Nette;


/**
 * Implementation of \ArrayAccess for IContainer.
 */
trait ArrayAccess
{
	/**
	 * Adds the component to the container.
	 * @param  string|int
	 * @param  IComponent
	 */
	public function offsetSet($name, $component): void
	{
		$this->addComponent($component, $name);
	}


	/**
	 * Returns component specified by name. Throws exception if component doesn't exist.
	 * @param  string|int
	 * @throws Nette\InvalidArgumentException
	 */
	public function offsetGet($name): IComponent
	{
		return $this->getComponent($name);
	}


	/**
	 * Does component specified by name exists?
	 * @param  string|int
	 */
	public function offsetExists($name): bool
	{
		return $this->getComponent($name, false) !== null;
	}


	/**
	 * Removes component from the container.
	 * @param  string|int
	 */
	public function offsetUnset($name): void
	{
		if ($component = $this->getComponent($name, false)) {
			$this->removeComponent($component);
		}
	}
}
