<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\ComponentModel;


/**
 * Recursive component iterator. See Container::getComponents().
 * @internal
 */
final class RecursiveComponentIterator extends \RecursiveArrayIterator implements \Countable
{
	/**
	 * Has the current element has children?
	 */
	public function hasChildren(): bool
	{
		return $this->current() instanceof IContainer;
	}


	/**
	 * The sub-iterator for the current element.
	 */
	public function getChildren(): self
	{
		return new self($this->current()->getComponents());
	}


	/**
	 * Returns the count of elements.
	 */
	public function count(): int
	{
		return iterator_count($this);
	}
}
