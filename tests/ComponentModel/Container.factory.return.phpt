<?php

/**
 * Test: Nette\ComponentModel\Container component named factory 4.
 */

use Nette\ComponentModel\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class TestClass extends Container
{

	public function createComponentB($name)
	{
		return new self;
	}

}


$a = new TestClass;
$b = $a->getComponent('b');

Assert::same('b', $b->getName());
Assert::count(1, $a->getComponents());


Assert::exception(function () use ($a) {
	$a->getComponent('B')->getName();
}, InvalidArgumentException::class, "Component with name 'B' does not exist, did you mean 'b'?");


$a->removeComponent($b);
Assert::count(0, $a->getComponents());
