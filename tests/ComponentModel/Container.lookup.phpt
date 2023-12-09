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
Assert::same('b-c-c2-d', $d->lookupPath(null));

// specified top
Assert::same($a, $d->lookup(A::class));
Assert::same('b-c-c2-d', $d->lookupPath(A::class));

// other
Assert::same($b, $d->lookup(B::class));
Assert::same('c-c2-d', $d->lookupPath(B::class));

// from top to bottom
Assert::same($c2, $d->lookup(C::class));
Assert::same('d', $d->lookupPath(C::class));


// self
Assert::exception(
	fn() => $d->lookup(D::class),
	Nette\InvalidStateException::class,
	"Component 'd' is not attached to 'D'.",
);

Assert::exception(
	fn() => $d->lookupPath(D::class),
	Nette\InvalidStateException::class,
	"Component 'd' is not attached to 'D'.",
);


// not exists
Assert::exception(
	fn() => $d->lookup('Unknown'),
	Nette\InvalidStateException::class,
	"Component 'd' is not attached to 'Unknown'.",
);

Assert::exception(
	fn() => $d->lookupPath('Unknown'),
	Nette\InvalidStateException::class,
	"Component 'd' is not attached to 'Unknown'.",
);
