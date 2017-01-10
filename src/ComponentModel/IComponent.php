<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\ComponentModel;


/**
 * Provides functionality required by all components.
 */
interface IComponent
{
	/** Separator for component names in path concatenation. */
	const NAME_SEPARATOR = '-';

	/**
	 * @return string|NULL
	 */
	function getName();

	/**
	 * Returns the container if any.
	 * @return IContainer|NULL
	 */
	function getParent();

	/**
	 * Sets the parent of this component.
	 * @param  IContainer
	 * @param  string
	 * @return static
	 */
	function setParent(IContainer $parent = NULL, $name = NULL);

}
