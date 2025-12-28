<?php declare(strict_types=1);

/**
 * Test: Nette\ComponentModel\Container component factory & remove inside.
 */

use Nette\ComponentModel\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class TestContainer extends Container
{
	public function createComponentB($name)
	{
		$this->addComponent($b = new self, $name);
		$this->removeComponent($b);
		return new self;
	}
}


$a = new TestContainer;
Assert::same('b', $a->getComponent('b')->getName());
