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
	public const NameSeparator = '-';

	/** @deprecated use IComponent::NameSeparator */
	public const NAME_SEPARATOR = self::NameSeparator;

	function getName(): ?string;

	/**
	 * Returns the parent container if any.
	 */
	function getParent(): ?IContainer;

	/**
	 * Sets the parent of this component.
	 * @return static
	 */
	function setParent(?IContainer $parent, ?string $name = null);
}
