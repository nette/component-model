<?php declare(strict_types=1);

/**
 * Test: Nette\ComponentModel\Container::attached()
 */

use Nette\ComponentModel\Container;
use Nette\ComponentModel\IComponent;
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
}


/**
 * Component that adds monitor in validateParent(). This is how Controls behave in nette/application.
 */
class MonitoringComponent extends BaseContainer
{
	protected function validateParent(Nette\ComponentModel\IContainer $parent): void
	{
		parent::validateParent($parent);
		@$this->monitor(self::class); // deprecated
	}
}


/**
 * Component that adds two monitors in validateParent().
 */
class DoubleMonitoringComponent extends BaseContainer
{
	protected function validateParent(Nette\ComponentModel\IContainer $parent): void
	{
		parent::validateParent($parent);
		@$this->monitor(RootContainer::class); // deprecated
		@$this->monitor(BaseContainer::class); // double
	}
}


test('attached and detached callbacks', function () {
	BaseContainer::$log = [];

	$d = new D;
	$d['e'] = new E;
	$b = new B;
	@$b->monitor(A::class); // deprecated
	$b['c'] = new C;
	@$b['c']->monitor(A::class); // deprecated
	$b['c']['d'] = $d;

	// 'a' becoming 'b' parent
	$a = new A;
	$a['b'] = $b;
	Assert::same([
		'C::ATTACHED(A)',
		'B::ATTACHED(A)',
	], BaseContainer::$log);

	BaseContainer::$log = [];

	// removing 'b' from 'a'
	unset($a['b']);
	Assert::same([
		'C::detached(A)',
		'B::detached(A)',
	], BaseContainer::$log);
});


test('lookup methods', function () {
	$d = new D;
	$d['e'] = new E;
	$b = new B;
	@$b->monitor(A::class); // deprecated
	$b['c'] = new C;
	@$b['c']->monitor(A::class); // deprecated
	$b['c']['d'] = $d;

	$a = new A;
	$a['b'] = $b;

	Assert::same('b-c-d-e', $d['e']->lookupPath(A::class));
	Assert::same($a, $d['e']->lookup(A::class));
	Assert::same('b-c-d-e', $d['e']->lookupPath(null));
	Assert::same($a, $d['e']->lookup(null));
	Assert::same('c-d-e', $d['e']->lookupPath(B::class));
	Assert::same($b, $d['e']->lookup(B::class));

	Assert::same($a['b-c'], $b['c']);
});


test('monitor in validateParent', function () {
	BaseContainer::$log = [];

	$presenter = new RootContainer;
	$presenter['control'] = new DoubleMonitoringComponent;
	$presenter['form'] = new MonitoringComponent;
	$presenter['form']['form'] = new MonitoringComponent;

	Assert::same([
		'DoubleMonitoringComponent::ATTACHED(RootContainer)',
		'MonitoringComponent::ATTACHED(MonitoringComponent)',
	], BaseContainer::$log);
});
