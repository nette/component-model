<?php declare(strict_types=1);

/**
 * Test: Nette\ComponentModel\Container component factory.
 */

use Nette\ComponentModel\Container;
use Nette\ComponentModel\IComponent;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class TestContainer extends Container
{
	public function createComponent(string $name): ?IComponent
	{
		return new stdClass;
	}
}


$a = new TestContainer;
$a->addComponent(new TestContainer, 'a');

Assert::exception(
	fn() => $a->getComponent('b'),
	TypeError::class,
);
