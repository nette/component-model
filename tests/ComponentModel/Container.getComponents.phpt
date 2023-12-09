<?php

/**
 * Test: Nette\ComponentModel\Container::getComponents().
 */

declare(strict_types=1);

use Nette\ComponentModel\Component;
use Nette\ComponentModel\Container;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class Button extends Component
{
}

class ComponentX extends Component
{
}

$c = new Container;

$c->addComponent(new Container, 'one');
$c->addComponent(new ComponentX, 'two');
$c->addComponent(new Button, 'button1');

$c->getComponent('one')->addComponent(new ComponentX, 'inner');
$c->getComponent('one')->addComponent(new Container, 'inner2');
$c->getComponent('one')->getComponent('inner2')->addComponent(new Button, 'button2');


// Normal
$list = $c->getComponents();
Assert::same([
	'one',
	'two',
	'button1',
], array_keys(iterator_to_array($list)));


// Filter
$list = $c->getComponents(false, Button::class);
Assert::same([
	'button1',
], array_keys(iterator_to_array($list)));


// RecursiveIteratorIterator
$list = new RecursiveIteratorIterator($c->getComponents(), 1);
Assert::same([
	'one',
	'inner',
	'inner2',
	'button2',
	'two',
	'button1',
], array_keys(iterator_to_array($list)));


// Recursive
$list = $c->getComponents(true);
Assert::same([
	'one',
	'inner',
	'inner2',
	'button2',
	'two',
	'button1',
], array_keys(iterator_to_array($list)));


// Recursive & filter I
$list = $c->getComponents(true, Button::class);
Assert::same([
	'button2',
	'button1',
], array_keys(iterator_to_array($list)));


// Recursive & filter II
$list = $c->getComponents(true, Nette\ComponentModel\Container::class);
Assert::same([
	'one',
	'inner2',
], array_keys(iterator_to_array($list)));
