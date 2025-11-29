<?php

/**
 * Test: Nette\ComponentModel\Container component named factory.
 */

declare(strict_types=1);

use Nette\ComponentModel\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class TestContainer extends Container
{
	public function createComponentB($name)
	{
	}
}


Assert::exception(function () {
	$a = new TestContainer;
	$a->getComponent('b');
}, Nette\UnexpectedValueException::class, 'Method TestContainer::createComponentB() did not return or create the desired component.');
