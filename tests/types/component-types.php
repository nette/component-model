<?php declare(strict_types=1);

/**
 * PHPStan type tests for Component::lookup() and related methods.
 * Run: vendor/bin/phpstan analyse tests/types
 */

use Nette\ComponentModel\Component;
use Nette\ComponentModel\Container;
use Nette\ComponentModel\IComponent;
use function PHPStan\Testing\assertType;


class TestContainer extends Container
{
}

/** @implements \ArrayAccess<string, IComponent> */
class TestContainerWithArrayAccess extends Container implements \ArrayAccess
{
	use \Nette\ComponentModel\ArrayAccess;
}

class TestComponent extends Component
{
}


function testLookup(TestComponent $component): void
{
	assertType(
		TestContainer::class,
		$component->lookup(TestContainer::class),
	);

	assertType(
		TestContainer::class . '|null',
		$component->lookup(TestContainer::class, throw: false),
	);

	assertType(
		IComponent::class,
		$component->lookup(null),
	);

	assertType(
		IComponent::class . '|null',
		$component->lookup(null, throw: false),
	);
}


function testLookupPath(TestComponent $component): void
{
	assertType(
		'string',
		$component->lookupPath(),
	);

	assertType(
		'string|null',
		$component->lookupPath(null, throw: false),
	);

	assertType(
		'string',
		$component->lookupPath(TestContainer::class),
	);

	assertType(
		'string|null',
		$component->lookupPath(TestContainer::class, throw: false),
	);
}


function testFluentInterface(TestContainer $container, TestComponent $component): void
{
	assertType(
		TestComponent::class,
		$component->setParent(null),
	);

	assertType(
		TestContainer::class,
		$container->addComponent($component, 'test'),
	);
}


function testMonitorCallback(TestComponent $component): void
{
	// monitor() callback should receive the monitored type
	$component->monitor(
		TestContainer::class,
		fn($obj) => assertType(TestContainer::class, $obj),
		fn($obj) => assertType(TestContainer::class, $obj),
	);
}


function testContainer(TestContainer $container): void
{
	assertType(
		IComponent::class,
		$container->getComponent('foo'),
	);

	assertType(
		IComponent::class . '|null',
		$container->getComponent('foo', throw: false),
	);

	assertType(
		'Nette\ComponentModel\IComponent[]',
		$container->getComponents(),
	);

	assertType(
		'list<Nette\ComponentModel\IComponent>',
		$container->getComponentTree(),
	);
}


function testContainerArrayAccess(TestContainerWithArrayAccess $container, IComponent $component): void
{
	assertType(
		IComponent::class,
		$container['foo'],
	);

	assertType(
		'bool',
		isset($container['foo']),
	);
}
