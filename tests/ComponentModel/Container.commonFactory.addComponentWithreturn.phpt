<?php declare(strict_types=1);

/**
 * Test: Nette\ComponentModel\Container component factory 3.
 */

use Nette\ComponentModel\Container;
use Nette\ComponentModel\IComponent;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class TestContainer extends Container
{
	public function createComponent(string $name): ?IComponent
	{
		$this->addComponent($component = new self, $name);
		return $component;
	}
}


$a = new TestContainer;
Assert::same('b', $a->getComponent('b')->getName());
