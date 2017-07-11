<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\ComponentModel;


/**
 * Containers are objects that logically contain zero or more IComponent components.
 */
interface IContainer extends IComponent
{

	/**
	 * Adds the specified component to the IContainer.
	 * @param  string|int $name
	 * @return static
	 */
	function addComponent(IComponent $component, $name);

	/**
	 * Removes a component from the IContainer.
	 */
	function removeComponent(IComponent $component): void;

	/**
	 * Returns single component.
	 * @param  string|int
	 */
	function getComponent($name): ?IComponent;

	/**
	 * Iterates over a components.
	 */
	function getComponents(bool $deep = FALSE, string $filterType = NULL): \Iterator;
}
