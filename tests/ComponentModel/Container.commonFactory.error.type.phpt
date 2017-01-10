<?php

/**
 * Test: Nette\ComponentModel\Container component factory.
 */

declare(strict_types=1);

use Nette\ComponentModel\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class TestClass extends Container
{

	public function createComponent($name)
	{
		return new stdClass;
	}

}


$a = new TestClass;
$a->addComponent(new TestClass, 'a');

Assert::exception(function () use ($a) {
	$a->getComponent('b');
}, Nette\UnexpectedValueException::class, 'Method createComponent() did not return Nette\ComponentModel\IComponent.');
