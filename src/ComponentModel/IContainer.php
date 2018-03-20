<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\ComponentModel;


/**
 * Containers are objects that logically contain zero or more IComponent components.
 */
interface IContainer extends IComponent
{
	/**
	 * Adds the component to the container.
	 * @param  string|int|null  $name
	 * @return static
	 */
	function addComponent(IComponent $component, $name);

	/**
	 * Removes the component from the container.
	 * @return void
	 */
	function removeComponent(IComponent $component);

	/**
	 * Returns component specified by name or path.
	 * @param  string|int  $name
	 * @return IComponent|null
	 */
	function getComponent($name);

	/**
	 * Iterates over descendants components.
	 * @return \Iterator
	 */
	function getComponents();
}
