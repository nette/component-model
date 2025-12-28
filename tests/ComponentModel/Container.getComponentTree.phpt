<?php declare(strict_types=1);

/**
 * Test: Nette\ComponentModel\Container::getComponentTree()
 */

use Nette\ComponentModel\Component;
use Nette\ComponentModel\Container;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class A extends Component
{
}

class B extends Component
{
}

$c = new Container;

$c->addComponent(new Container, 'container');
$c->addComponent(new B, 'b');
$c->addComponent(new A, 'a');

$c->getComponent('container')->addComponent(new B, 'inner_b');
$c->getComponent('container')->addComponent(new Container, 'inner_container');
$c->getComponent('container')->getComponent('inner_container')->addComponent(new A, 'inner_a');


$list = $c->getComponentTree();
Assert::same([
	'container',
	'inner_b',
	'inner_container',
	'inner_a',
	'b',
	'a',
], array_map(fn($c) => $c->getName(), $list));
