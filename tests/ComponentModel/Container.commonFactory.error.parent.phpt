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
		return $this->getComponent('a');
	}
}


$a = new TestContainer;
$a->addComponent(new TestContainer, 'a');

Assert::exception(
	fn() => $a->getComponent('b'),
	Nette\InvalidStateException::class,
	"Component 'a' already has a parent.",
);
