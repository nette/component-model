<?php

/**
 * Test: Nette\ComponentModel\Container component named factory 4.
 */

use Nette\ComponentModel\Container,
	Tester\Assert;


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

Assert::same( 'b', $b->name );
Assert::count( 1, $a->getComponents() );


Assert::exception(function() use ($a) {
	$a->getComponent('B')->name;
}, 'InvalidArgumentException', "Component with name 'B' does not exist.");


$a->removeComponent($b);
Assert::count( 0, $a->getComponents() );
