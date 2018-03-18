<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

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
	 * @return void
	 */
	public function offsetSet($name, $component)
	{
		$this->addComponent($component, $name);
	}


	/**
	 * Returns component specified by name. Throws exception if component doesn't exist.
	 * @param  string|int
	 * @return IComponent
	 * @throws Nette\InvalidArgumentException
	 */
	public function offsetGet($name)
	{
		return $this->getComponent($name);
	}


	/**
	 * Does component specified by name exists?
	 * @param  string|int
	 * @return bool
	 */
	public function offsetExists($name)
	{
		return $this->getComponent($name, false) !== null;
	}


	/**
	 * Removes component from the container.
	 * @param  string|int
	 * @return void
	 */
	public function offsetUnset($name)
	{
		if ($component = $this->getComponent($name, false)) {
			$this->removeComponent($component);
		}
	}
}
