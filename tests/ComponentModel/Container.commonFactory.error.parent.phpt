<?php

/**
 * Test: Nette\ComponentModel\Container component factory.
 */

declare(strict_types=1);

use Nette\ComponentModel\Container;
use Nette\ComponentModel\IComponent;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class TestClass extends Container
{
	public function createComponent(string $name): ?IComponent
	{
		return $this->getComponent('a');
	}
}


$a = new TestClass;
$a->addComponent(new TestClass, 'a');

Assert::exception(
	fn() => $a->getComponent('b'),
	Nette\InvalidStateException::class,
	"Component 'a' already has a parent.",
);
