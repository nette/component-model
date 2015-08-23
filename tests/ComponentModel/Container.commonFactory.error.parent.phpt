<?php

/**
 * Test: Nette\ComponentModel\Container component factory.
 */

use Nette\ComponentModel\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class TestClass extends Container
{

	public function createComponent($name)
	{
		return $this->getComponent('a');
	}

}


$a = new TestClass;
$a->addComponent(new TestClass, 'a');

Assert::exception(function () use ($a) {
	$a->getComponent('b');
}, Nette\InvalidStateException::class, "Component 'a' already has a parent.");
