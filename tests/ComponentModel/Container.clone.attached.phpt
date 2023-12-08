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

	public static array $log = [];


	public function attached(IComponent $obj): void
	{
		self::$log[] = static::class . '::ATTACHED(' . $obj::class . ')';
	}


	public function detached(IComponent $obj): void
	{
		self::$log[] = static::class . '::detached(' . $obj::class . ')';
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
	BaseContainer::$log = [];

	$a = new A;
	$a['b'] = new B;
	$a['b']['c'] = new C;
	$a['b']['c']['d'] = new D;
	$a['b']['c']['d']['e'] = new E;

	@$a['b']->monitor(A::class); // deprecated
	@$a['b']['c']->monitor(A::class); // deprecated

	Assert::same([
		'B::ATTACHED(A)',
		'C::ATTACHED(A)',
	], BaseContainer::$log);

	Assert::same('b-c-d-e', $a['b']['c']['d']['e']->lookupPath(A::class));
});


test('clone detaches from parent', function () {
	$a = new A;
	$a['b'] = new B;
	$a['b']['c'] = new C;
	$a['b']['c']['d'] = new D;
	$a['b']['c']['d']['e'] = new E;

	@$a['b']->monitor(A::class); // deprecated
	@$a['b']['c']->monitor(A::class); // deprecated

	BaseContainer::$log = [];

	// ==> clone 'c'
	$dolly = clone $a['b']['c'];

	Assert::same([
		'C::detached(A)',
	], BaseContainer::$log);

	Assert::null($dolly['d']['e']->lookupPath(A::class, throw: false));
	Assert::same('d-e', $dolly['d']['e']->lookupPath(C::class));
});


test('clone subtree', function () {
	$a = new A;
	$a['b'] = new B;
	$a['b']['c'] = new C;
	$a['b']['c']['d'] = new D;
	$a['b']['c']['d']['e'] = new E;

	@$a['b']->monitor(A::class); // deprecated
	@$a['b']['c']->monitor(A::class); // deprecated

	BaseContainer::$log = [];

	// ==> clone 'b'
	$dolly = clone $a['b'];

	Assert::same([
		'C::detached(A)',
		'B::detached(A)',
	], BaseContainer::$log);
});


test('reattach cloned component', function () {
	$a = new A;
	$a['b'] = new B;
	$a['b']['c'] = new C;
	$a['b']['c']['d'] = new D;
	$a['b']['c']['d']['e'] = new E;

	@$a['b']->monitor(A::class); // deprecated
	@$a['b']['c']->monitor(A::class); // deprecated

	$dolly = clone $a['b'];

	BaseContainer::$log = [];

	// ==> a['dolly'] = 'b'
	$a['dolly'] = $dolly;

	Assert::same([
		'C::ATTACHED(A)',
		'B::ATTACHED(A)',
	], BaseContainer::$log);

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
