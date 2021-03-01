<?php

/**
 * Test: Nette\ComponentModel\Container and '0' name.
 */

declare(strict_types=1);

use Nette\ComponentModel\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$container = new Container;
$container->addComponent($c0 = new Container, '0');
Assert::same($c0, $container->getComponent('0'));
Assert::same('0', $container->getComponent('0')->getName());

$container->addComponent($c1 = new Container, '1', '0');
Assert::same(
	[1 => $c1, 0 => $c0],
	(array) $container->getComponents(),
);
