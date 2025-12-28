<?php declare(strict_types=1);

/**
 * Test: Nette\ComponentModel\Container component named factory 5.
 */

use Nette\ComponentModel\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class TestContainer extends Container
{
	public function createComponentB($name)
	{
		$this->addComponent(new self, $name);
	}
}


$a = new TestContainer;
Assert::same('b', $a->getComponent('b')->getName());


Assert::exception(
	fn() => $a->getComponent('B')->getName(),
	InvalidArgumentException::class,
	"Component with name 'B' does not exist, did you mean 'b'?",
);
