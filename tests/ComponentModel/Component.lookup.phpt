<?php declare(strict_types=1);

/**
 * Test: Nette\ComponentModel\Container lookup.
 */

use Nette\ComponentModel\Container;
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


$a = new A;
$a['b'] = $b = new B;
$a['b']['c'] = $c = new C;
$a['b']['c']['c2'] = $c2 = new C;
$a['b']['c']['c2']['d'] = $d = new D;

// top
Assert::same($a, $d->lookup(null));
Assert::same('b-c-c2-d', $d->lookupPath());

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


// the answer follows the tree, also after a lookup was made
$sub = new B;
$sub['x'] = $x = new C;
Assert::same($sub, $x->lookup(null));
Assert::same('x', $x->lookupPath());
Assert::null($x->lookup(A::class, throw: false));

$top = new A;
$top['sub'] = $sub;
Assert::same($top, $x->lookup(null));
Assert::same('sub-x', $x->lookupPath());
Assert::same($top, $x->lookup(A::class));

unset($top['sub']);
Assert::same($sub, $x->lookup(null));
Assert::null($x->lookup(A::class, throw: false));


// a clone answers with the cloned ancestors
$dolly = clone $a;
Assert::same($dolly, $dolly['b']['c']['c2']['d']->lookup(A::class));
Assert::same($dolly['b'], $dolly['b']['c']['c2']['d']->lookup(B::class));
Assert::same($a, $d->lookup(A::class));


// a lookup made by a detached handler, before the parent is cleared, does not outlive the removal
$top = new A;
$top['sub'] = $sub = new B;
$sub['x'] = $x = new C;
$x->monitor(A::class, detached: function () use ($x, $top): void {
	Assert::same($top, $x->lookup(A::class));
	Assert::same('sub-x', $x->lookupPath());
});
unset($top['sub']);
Assert::null($x->lookup(A::class, throw: false));
Assert::same($sub, $x->lookup(null));
Assert::same('x', $x->lookupPath());


// renaming changes the paths below
$top = new A;
$top['sub'] = $sub = new B;
$sub['x'] = $x = new C;
Assert::same('sub-x', $x->lookupPath(A::class));
unset($top['sub']);
$sub->setParent(null, 'renamed');
$top->addComponent($sub, null);
Assert::same('renamed-x', $x->lookupPath(A::class));


// a lookup in a __clone() override, made while the clone is still attached above, does not outlive the cloning
class LookingOnClone extends C
{
	public function __clone()
	{
		parent::__clone();
		$this->lookup(A::class, throw: false);
	}
}

$top = new A;
$top['sub'] = $sub = new B;
$sub['x'] = new LookingOnClone;
$dolly = clone $sub;
Assert::null($dolly->getParent());
Assert::null($dolly['x']->lookup(A::class, throw: false));
