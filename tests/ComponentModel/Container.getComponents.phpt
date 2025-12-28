<?php declare(strict_types=1);

/**
 * Test: Nette\ComponentModel\Container::getComponents().
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


// Normal
$list = $c->getComponents();
Assert::same([
	'container',
	'b',
	'a',
], array_keys($list));

// Filter
$list = $c->getComponents(false, A::class);
Assert::same([
	'a',
], array_keys($list));


// Recursive
$list = $c->getComponents(true);
Assert::same([
	'container',
	'inner_b',
	'inner_container',
	'inner_a',
	'b',
	'a',
], array_keys(iterator_to_array($list)));
// again
Assert::same([
	'container',
	'inner_b',
	'inner_container',
	'inner_a',
	'b',
	'a',
], array_keys(iterator_to_array($list)));


// Recursive & filter I
$list = $c->getComponents(true, A::class);
Assert::same([
	'inner_a',
	'a',
], array_keys(iterator_to_array($list)));
// again
Assert::same([
	'inner_a',
	'a',
], array_keys(iterator_to_array($list)));


// Recursive & filter II
$list = $c->getComponents(true, Nette\ComponentModel\Container::class);
Assert::same([
	'container',
	'inner_container',
], array_keys(iterator_to_array($list)));
