<?php

/**
 * Test: Nette\ComponentModel\Container and '0' name.
 */

declare(strict_types=1);

use Nette\ComponentModel\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$container = new Container;
$container->addComponent(new Container, '0');
Assert::same('0', $container->getComponent('0')->getName());
