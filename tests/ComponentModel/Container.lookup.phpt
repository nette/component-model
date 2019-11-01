<?php

/**
 * Test: Nette\ComponentModel\Container lookup.
 */

declare(strict_types=1);

use Nette\ComponentModel\Container;
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


$a = new A;
$a['b'] = $b = new B;
$a['b']['c'] = $c = new C;
$a['b']['c']['c2'] = $c2 = new C;
$a['b']['c']['c2']['d'] = $d = new D;

// top
Assert::same($a, $d->lookup(null));
Assert::same($a, $d->lookupIfExists(null));
Assert::same('b-c-c2-d', $d->lookupPath(null));
Assert::same('b-c-c2-d', $d->lookupPathIfExists(null));

// specified top
Assert::same($a, $d->lookup(A::class));
Assert::same($a, $d->lookupIfExists(A::class));
Assert::same('b-c-c2-d', $d->lookupPath(A::class));
Assert::same('b-c-c2-d', $d->lookupPathIfExists(A::class));

// other
Assert::same($b, $d->lookup(B::class));
Assert::same($b, $d->lookupIfExists(B::class));
Assert::same('c-c2-d', $d->lookupPath(B::class));
Assert::same('c-c2-d', $d->lookupPathIfExists(B::class));

// from top to bottom
Assert::same($c2, $d->lookup(C::class));
Assert::same($c2, $d->lookupIfExists(C::class));
Assert::same('d', $d->lookupPath(C::class));
Assert::same('d', $d->lookupPathIfExists(C::class));


// self
Assert::exception(function () use ($d) {
	$d->lookup(D::class);
}, Nette\InvalidStateException::class, "Component 'd' is not attached to 'D'.");

Assert::null($d->lookupIfExists(D::class));

Assert::exception(function () use ($d) {
	$d->lookupPath(D::class);
}, Nette\InvalidStateException::class, "Component 'd' is not attached to 'D'.");

Assert::null($d->lookupPathIfExists(D::class));


// not exists
Assert::exception(function () use ($d) {
	$d->lookup('Unknown');
}, Nette\InvalidStateException::class, "Component 'd' is not attached to 'Unknown'.");

Assert::null($d->lookupIfExists('Unknown'));

Assert::exception(function () use ($d) {
	$d->lookupPath('Unknown');
}, Nette\InvalidStateException::class, "Component 'd' is not attached to 'Unknown'.");

Assert::null($d->lookupPathIfExists('Unknown'));
