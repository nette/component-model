<?php

/**
 * Test: Nette\ComponentModel\Container component factory & remove inside.
 */

declare(strict_types=1);

use Nette\ComponentModel\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class TestClass extends Container
{
	public function createComponentB($name)
	{
		$this->addComponent($b = new self, $name);
		$this->removeComponent($b);
		return new self;
	}
}


$a = new TestClass;
Assert::same('b', $a->getComponent('b')->getName());
