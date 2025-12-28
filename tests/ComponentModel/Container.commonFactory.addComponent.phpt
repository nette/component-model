<?php declare(strict_types=1);

/**
 * Test: Nette\ComponentModel\Container component factory 2.
 */

use Nette\ComponentModel\Container;
use Nette\ComponentModel\IComponent;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class TestContainer extends Container
{
	public function createComponent(string $name): ?IComponent
	{
		$this->addComponent(new self, $name);
		return null;
	}
}


$a = new TestContainer;
Assert::same('b', $a->getComponent('b')->getName());
