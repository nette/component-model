<?php

/**
 * Test: Nette\ComponentModel\Container component named factory.
 */

use Nette\ComponentModel\Container,
	Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class TestClass extends Container
{

	public function createComponentB($name)
	{
	}

}


Assert::exception(function() {
	$a = new TestClass;
	$a->getComponent('b');
}, 'Nette\UnexpectedValueException', 'Method TestClass::createComponentB() did not return or create the desired component.');
