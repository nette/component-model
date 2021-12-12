<?php

/**
 * Test: Nette\ComponentModel\Container cloning.
 */

declare(strict_types=1);

use Nette\ComponentModel\Container;
use Nette\ComponentModel\IComponent;
use Nette\ComponentModel\IContainer;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class TestClass extends Container implements ArrayAccess
{
	use Nette\ComponentModel\ArrayAccess;

	public function attached(IComponent $obj): void
	{
		Notes::add(static::class . '::ATTACHED(' . get_class($obj) . ')');
	}


	public function detached(IComponent $obj): void
	{
		Notes::add(static::class . '::detached(' . get_class($obj) . ')');
	}
}


function export($obj)
{
	$res = ['(' . get_class($obj) . ')' => $obj->getName()];
	if ($obj instanceof IContainer) {
		foreach ($obj->getComponents() as $name => $child) {
			$res['children'][$name] = export($child);
		}
	}

	return $res;
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


$a = new A;
$a['b'] = new B;
$a['b']['c'] = new C;
$a['b']['c']['d'] = new D;
$a['b']['c']['d']['e'] = new E;

$a['b']->monitor('a', createAttached($a['b']), createDetached($a['b']));
$a['b']['c']->monitor('a', createAttached($a['b']['c']), createDetached($a['b']['c']));

Assert::same([
	'ATTACHED(A, B)',
	'ATTACHED(A, C)',
], Notes::fetch());

Assert::same('b-c-d-e', $a['b']['c']['d']['e']->lookupPath(A::class));


// ==> clone 'c'
$dolly = clone $a['b']['c'];

Assert::same([
	'detached(A, C)',
], Notes::fetch());

Assert::null($dolly['d']['e']->lookupPath('A', false));

Assert::same('d-e', $dolly['d']['e']->lookupPath(C::class));


// ==> clone 'b'
$dolly = clone $a['b'];

Assert::same([
	'detached(A, C)',
	'detached(A, B)',
], Notes::fetch());


// ==> a['dolly'] = 'b'
$a['dolly'] = $dolly;

Assert::same([
	'ATTACHED(A, C)',
	'ATTACHED(A, B)',
], Notes::fetch());

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
