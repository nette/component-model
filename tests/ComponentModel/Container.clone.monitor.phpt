<?php declare(strict_types=1);

/**
 * Test: Nette\ComponentModel\Container cloning.
 */

use Nette\ComponentModel\Container;
use Nette\ComponentModel\IComponent;
use Nette\ComponentModel\IContainer;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class BaseContainer extends Container implements ArrayAccess
{
	use Nette\ComponentModel\ArrayAccess;

	public function attached(IComponent $obj): void
	{
		throw new Nette\ShouldNotHappenException;
	}


	public function detached(IComponent $obj): void
	{
		throw new Nette\ShouldNotHappenException;
	}
}


function export($obj)
{
	$res = ['(' . $obj::class . ')' => $obj->getName()];
	if ($obj instanceof IContainer) {
		foreach ($obj->getComponents() as $name => $child) {
			$res['children'][$name] = export($child);
		}
	}

	return $res;
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


test('monitor registration', function () {
	$log = [];

	$a = new A;
	$a['b'] = new B;
	$a['b']['c'] = new C;
	$a['b']['c']['d'] = new D;
	$a['b']['c']['d']['e'] = new E;

	$a['b']->monitor(
		A::class,
		function (IComponent $obj) use (&$log) { $log[] = 'ATTACHED(' . $obj::class . ', B)'; },
		function (IComponent $obj) use (&$log) { $log[] = 'detached(' . $obj::class . ', B)'; },
	);
	$a['b']['c']->monitor(
		A::class,
		function (IComponent $obj) use (&$log) { $log[] = 'ATTACHED(' . $obj::class . ', C)'; },
		function (IComponent $obj) use (&$log) { $log[] = 'detached(' . $obj::class . ', C)'; },
	);

	Assert::same([
		'ATTACHED(A, B)',
		'ATTACHED(A, C)',
	], $log);

	Assert::same('b-c-d-e', $a['b']['c']['d']['e']->lookupPath(A::class));
});


test('clone detaches from parent', function () {
	$log = [];

	$a = new A;
	$a['b'] = new B;
	$a['b']['c'] = new C;
	$a['b']['c']['d'] = new D;
	$a['b']['c']['d']['e'] = new E;

	$a['b']->monitor(
		A::class,
		function (IComponent $obj) use (&$log) { $log[] = 'ATTACHED(' . $obj::class . ', B)'; },
		function (IComponent $obj) use (&$log) { $log[] = 'detached(' . $obj::class . ', B)'; },
	);
	$a['b']['c']->monitor(
		A::class,
		function (IComponent $obj) use (&$log) { $log[] = 'ATTACHED(' . $obj::class . ', C)'; },
		function (IComponent $obj) use (&$log) { $log[] = 'detached(' . $obj::class . ', C)'; },
	);

	$log = [];

	// ==> clone 'c'
	$dolly = clone $a['b']['c'];

	Assert::same([
		'detached(A, C)',
	], $log);

	Assert::null($dolly['d']['e']->lookupPath(A::class, throw: false));
	Assert::same('d-e', $dolly['d']['e']->lookupPath(C::class));
});


test('clone subtree', function () {
	$log = [];

	$a = new A;
	$a['b'] = new B;
	$a['b']['c'] = new C;
	$a['b']['c']['d'] = new D;
	$a['b']['c']['d']['e'] = new E;

	$a['b']->monitor(
		A::class,
		function (IComponent $obj) use (&$log) { $log[] = 'ATTACHED(' . $obj::class . ', B)'; },
		function (IComponent $obj) use (&$log) { $log[] = 'detached(' . $obj::class . ', B)'; },
	);
	$a['b']['c']->monitor(
		A::class,
		function (IComponent $obj) use (&$log) { $log[] = 'ATTACHED(' . $obj::class . ', C)'; },
		function (IComponent $obj) use (&$log) { $log[] = 'detached(' . $obj::class . ', C)'; },
	);

	$log = [];

	// ==> clone 'b'
	$dolly = clone $a['b'];

	Assert::same([
		'detached(A, C)',
		'detached(A, B)',
	], $log);
});


test('reattach cloned component', function () {
	$log = [];

	$a = new A;
	$a['b'] = new B;
	$a['b']['c'] = new C;
	$a['b']['c']['d'] = new D;
	$a['b']['c']['d']['e'] = new E;

	$a['b']->monitor(
		A::class,
		function (IComponent $obj) use (&$log) { $log[] = 'ATTACHED(' . $obj::class . ', B)'; },
		function (IComponent $obj) use (&$log) { $log[] = 'detached(' . $obj::class . ', B)'; },
	);
	$a['b']['c']->monitor(
		A::class,
		function (IComponent $obj) use (&$log) { $log[] = 'ATTACHED(' . $obj::class . ', C)'; },
		function (IComponent $obj) use (&$log) { $log[] = 'detached(' . $obj::class . ', C)'; },
	);

	$log = [];

	$dolly = clone $a['b'];

	$log = [];

	// ==> a['dolly'] = 'b'
	$a['dolly'] = $dolly;

	Assert::same([
		'ATTACHED(A, B)',
		'ATTACHED(A, C)',
	], $log);

	Assert::same([
		'(A)' => null,
		'children' => [
			'b' => [
				'(B)' => 'b',
				'children' => [
					'c' => [
						'(C)' => 'c',
						'children' => [
							'd' => [
								'(D)' => 'd',
								'children' => [
									'e' => [
										'(E)' => 'e',
									],
								],
							],
						],
					],
				],
			],
			'dolly' => [
				'(B)' => 'dolly',
				'children' => [
					'c' => [
						'(C)' => 'c',
						'children' => [
							'd' => [
								'(D)' => 'd',
								'children' => [
									'e' => [
										'(E)' => 'e',
									],
								],
							],
						],
					],
				],
			],
		],
	], export($a));
});
