<?php declare(strict_types=1);

/**
 * Test: Nette\ComponentModel\Container::monitor()
 */

use Nette\ComponentModel\Container;
use Nette\ComponentModel\IComponent;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class BaseContainer extends Container implements ArrayAccess
{
	use Nette\ComponentModel\ArrayAccess;
}


class A extends BaseContainer
{
}

class B extends BaseContainer
{
}

class C extends BaseContainer
{
}

class D extends BaseContainer
{
}

class E extends BaseContainer
{
}


class RootContainer extends BaseContainer
{
	public static array $log = [];
}


/**
 * Component that adds monitor in validateParent(). This is how Controls behave in nette/application.
 */
class MonitoringComponent extends BaseContainer
{
	protected function validateParent(Nette\ComponentModel\IContainer $parent): void
	{
		parent::validateParent($parent);
		$this->monitor(self::class, fn($obj) => RootContainer::$log[] = 'ATTACHED ' . $obj::class);
	}
}


function myGlobalHandler(Nette\ComponentModel\IComponent $obj): void
{
	RootContainer::$log[] = $obj::class;
}


test('monitor with handlers', function () {
	$log = [];

	$d = new D;
	$d['e'] = new E;
	$b = new B;
	$b->monitor(
		A::class,
		function (IComponent $obj) use (&$log) { $log[] = 'ATTACHED(' . $obj::class . ', B)'; },
		function (IComponent $obj) use (&$log) { $log[] = 'detached(' . $obj::class . ', B)'; },
	);
	$b['c'] = new C;
	$b['c']->monitor(
		A::class,
		function (IComponent $obj) use (&$log) { $log[] = 'ATTACHED(' . $obj::class . ', C)'; },
		function (IComponent $obj) use (&$log) { $log[] = 'detached(' . $obj::class . ', C)'; },
	);
	$b['c']['d'] = $d;

	// 'a' becoming 'b' parent
	$a = new A;
	$a['b'] = $b;
	Assert::same([
		'ATTACHED(A, B)',
		'ATTACHED(A, C)',
	], $log);

	$log = [];

	// removing 'b' from 'a'
	unset($a['b']);
	Assert::same([
		'detached(A, C)',
		'detached(A, B)',
	], $log);

	$log = [];

	// 'a' becoming 'b' parent
	$a['b'] = $b;

	Assert::same('b-c-d-e', $d['e']->lookupPath(A::class));
	Assert::same($a, $d['e']->lookup(A::class));
	Assert::same('b-c-d-e', $d['e']->lookupPath(null));
	Assert::same($a, $d['e']->lookup(null));
	Assert::same('c-d-e', $d['e']->lookupPath(B::class));
	Assert::same($b, $d['e']->lookup(B::class));

	Assert::same($a['b-c'], $b['c']);
});


test('handler called immediately when ancestor already exists', function () {
	$log = [];
	$handler = function ($obj) use (&$log) { $log[] = 'ATTACHED ' . $obj::class; };

	$root = new RootContainer;
	$child = new BaseContainer;
	$root['child'] = $child;

	// monitor() called after attachment - handler should fire immediately
	$child->monitor(RootContainer::class, $handler);
	Assert::same(['ATTACHED RootContainer'], $log);

	// Second monitor() with same handler - should NOT fire again
	$child->monitor(RootContainer::class, $handler);
	Assert::same(['ATTACHED RootContainer'], $log);
});


test('same handler called once for overlapping monitors', function () {
	$log = [];
	$handler = function ($obj) use (&$log) { $log[] = $obj::class; };

	$root = new RootContainer;
	$child = new BaseContainer;

	// Register same handler for two types - both will match RootContainer
	$child->monitor(RootContainer::class, $handler, $handler);
	$child->monitor(BaseContainer::class, $handler, $handler);

	$root['child'] = $child;

	// RootContainer matches both RootContainer::class and BaseContainer::class monitors,
	// but the same handler should be called only once
	Assert::same(['RootContainer'], $log);

	$log = [];
	unset($root['child']);
	Assert::same(['RootContainer'], $log);
});


test('same handler for multiple types matching different ancestors', function () {
	$log = [];
	$handler = function ($obj) use (&$log) { $log[] = $obj::class; };

	// Hierarchy: A > B > component
	$a = new A;
	$b = new B;
	$component = new BaseContainer;

	// Component monitors both A and B with same handler
	$component->monitor(A::class, $handler, $handler);
	$component->monitor(B::class, $handler, $handler);

	$b['component'] = $component;
	$a['b'] = $b;

	// Handler called twice - once for each matched ancestor
	Assert::same(['B', 'A'], $log);

	$log = [];
	unset($b['component']);
	Assert::same(['B', 'A'], $log);
});


test('same handler deduplication during single attach', function () {
	$log = [];
	$handler = function ($obj) use (&$log) { $log[] = $obj::class; };

	$root = new RootContainer;
	$child = new BaseContainer;

	// Register same handler twice for same type
	$child->monitor(RootContainer::class, $handler, $handler);
	$child->monitor(RootContainer::class, $handler, $handler);

	$root['child'] = $child;

	// Handler should be called only once due to deduplication
	Assert::same(['RootContainer'], $log);

	$log = [];
	unset($root['child']);
	Assert::same(['RootContainer'], $log);
});


test('string callable and first-class callable are deduplicated', function () {
	RootContainer::$log = [];

	$root = new RootContainer;
	$child = new BaseContainer;

	// Register handler as string callable
	$child->monitor(RootContainer::class, 'myGlobalHandler', 'myGlobalHandler');
	// Register same handler as first-class callable
	$child->monitor(RootContainer::class, myGlobalHandler(...), myGlobalHandler(...));

	$root['child'] = $child;

	// Handler should be called only once due to deduplication
	Assert::same(['RootContainer'], RootContainer::$log);

	RootContainer::$log = [];
	unset($root['child']);
	Assert::same(['RootContainer'], RootContainer::$log);
});


test('different handlers for same type all called', function () {
	$log = [];
	$handler1 = function ($obj) use (&$log) { $log[] = 'handler1: ' . $obj::class; };
	$handler2 = function ($obj) use (&$log) { $log[] = 'handler2: ' . $obj::class; };

	$root = new RootContainer;
	$child = new BaseContainer;

	// Register different handlers for same type
	$child->monitor(RootContainer::class, $handler1, $handler1);
	$child->monitor(RootContainer::class, $handler2, $handler2);

	$root['child'] = $child;

	// Both handlers should be called
	Assert::same(['handler1: RootContainer', 'handler2: RootContainer'], $log);

	$log = [];
	unset($root['child']);
	Assert::same(['handler1: RootContainer', 'handler2: RootContainer'], $log);
});


test('monitor attached in validateParent', function () {
	RootContainer::$log = [];

	$presenter = new RootContainer;
	$presenter['form'] = new MonitoringComponent;
	$presenter['form']['form'] = new MonitoringComponent;

	Assert::same([
		'ATTACHED MonitoringComponent',
	], RootContainer::$log);

	RootContainer::$log = [];
	unset($presenter['form']);
	Assert::same([], RootContainer::$log);
});
