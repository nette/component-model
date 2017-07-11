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
	 * Adds the specified component to the IContainer.
	 * @param  IComponent
	 * @param  string|int
	 * @return static
	 */
	function addComponent(IComponent $component, $name);

	/**
	 * Removes a component from the IContainer.
	 * @param  IComponent
	 * @return void
	 */
	function removeComponent(IComponent $component);

	/**
	 * Returns single component.
	 * @param  string|int
	 * @return IComponent|null
	 */
	function getComponent($name);

	/**
	 * Iterates over a components.
	 * @param  bool
	 * @param  string
	 * @return \Iterator
	 */
	function getComponents($deep = false, $filterType = null);
}
