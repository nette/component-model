<?php

/**
 * Test: Nette\ComponentModel\Container::monitor()
 */

declare(strict_types=1);

use Nette\ComponentModel\Container;
use Nette\ComponentModel\IComponent;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class TestClass extends Container implements ArrayAccess
{
	use Nette\ComponentModel\ArrayAccess;
}


class A extends TestClass
{
}
class B extends TestClass
{
}
class C extends TestClass
{
}
class D extends TestClass
{
}
class E extends TestClass
{
}


function createAttached(IComponent $sender)
{
	return function (IComponent $obj) use ($sender) {
		Notes::add('ATTACHED(' . get_class($obj) . ', ' . get_class($sender) . ')');
	};
}


function createDetached(IComponent $sender)
{
	return function (IComponent $obj) use ($sender) {
		Notes::add('detached(' . get_class($obj) . ', ' . get_class($sender) . ')');
	};
}


$d = new D;
$d['e'] = new E;
$b = new B;
$b->monitor('a', createAttached($b), createDetached($b));
$b['c'] = new C;
$b['c']->monitor('a', createAttached($b['c']), createDetached($b['c']));
$b['c']['d'] = $d;

// 'a' becoming 'b' parent
$a = new A;
$a['b'] = $b;
Assert::same([
	'ATTACHED(A, C)',
	'ATTACHED(A, B)',
], Notes::fetch());


// removing 'b' from 'a'
unset($a['b']);
Assert::same([
	'detached(A, C)',
	'detached(A, B)',
], Notes::fetch());

// 'a' becoming 'b' parent
$a['b'] = $b;

Assert::same('b-c-d-e', $d['e']->lookupPath(A::class));
Assert::same($a, $d['e']->lookup(A::class));
Assert::same('b-c-d-e', $d['e']->lookupPath(null));
Assert::same($a, $d['e']->lookup(null));
Assert::same('c-d-e', $d['e']->lookupPath(B::class));
Assert::same($b, $d['e']->lookup(B::class));

Assert::same($a['b-c'], $b['c']);
Notes::fetch(); // clear


class FooForm extends TestClass
{
	protected function validateParent(\Nette\ComponentModel\IContainer $parent): void
	{
		parent::validateParent($parent);
		$this->monitor(self::class, createAttached($this));
	}
}


class FooControl extends TestClass
{
	protected function validateParent(\Nette\ComponentModel\IContainer $parent): void
	{
		parent::validateParent($parent);
		$this->monitor('FooPresenter', [$this, 'myAttached']);
		$this->monitor('TestClass', [$this, 'myAttached']); // double
	}


	protected function myAttached(TestClass $obj)
	{
		Notes::add('ATTACHED(' . get_class($obj) . ', ' . static::class . ')');
	}
}

class FooPresenter extends TestClass
{
}

$presenter = new FooPresenter;
$presenter['control'] = new FooControl;
$presenter['form'] = new FooForm;
$presenter['form']['form'] = new FooForm;

Assert::same([
	'ATTACHED(FooPresenter, FooControl)',
	'ATTACHED(FooForm, FooForm)',
], Notes::fetch());

unset($presenter['form'], $presenter['control']);


Assert::same([], Notes::fetch());
