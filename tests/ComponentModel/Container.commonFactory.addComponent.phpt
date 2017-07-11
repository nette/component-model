<?php

/**
 * Test: Nette\ComponentModel\Container component factory 2.
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
		$this->addComponent(new self, $name);
		return null;
	}
}


$a = new TestClass;
Assert::same('b', $a->getComponent('b')->getName());
