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


function handler(IComponent $sender, string $label): Closure
{
	return function (IComponent $obj) use ($sender, $label) {
		Notes::add($label . '(' . get_class($obj) . ', ' . get_class($sender) . ')');
	};
}


$d = new D;
$d['e'] = new E;
$b = new B;
$b->monitor(A::class, handler($b, 'ATTACHED'), handler($b, 'detached'));
$b['c'] = new C;
$b['c']->monitor(A::class, handler($b['c'], 'ATTACHED'), handler($b['c'], 'detached'));
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
	protected function validateParent(Nette\ComponentModel\IContainer $parent): void
	{
		parent::validateParent($parent);
		$this->monitor(self::class, handler($this, 'ATTACHED'));
	}
}


class FooControl extends TestClass
{
	protected function validateParent(Nette\ComponentModel\IContainer $parent): void
	{
		parent::validateParent($parent);
		$this->monitor(FooPresenter::class, [$this, 'myAttached']);
		$this->monitor(TestClass::class, [$this, 'myAttached']); // double
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
