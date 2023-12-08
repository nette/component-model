<?php

/**
 * Test: Nette\ComponentModel\Container::getComponentTree()
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


$list = $c->getComponentTree();
Assert::same([
	'one',
	'inner',
	'inner2',
	'button2',
	'two',
	'button1',
], array_map(fn($c) => $c->getName(), $list));
