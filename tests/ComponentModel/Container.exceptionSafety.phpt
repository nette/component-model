<?php declare(strict_types=1);

/**
 * Test: Nette\ComponentModel\Container keeps the tree consistent when a listener or __clone() throws.
 */

use Nette\ComponentModel\{Component, Container};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class ThrowingOnDetach extends Component
{
	private bool $throwing = true;


	public function setupMonitoring(): void
	{
		$this->monitor(Container::class, detached: function (): void {
			if ($this->throwing) {
				throw new RuntimeException('detach failed');
			}
		});
	}


	public function stopThrowing(): void
	{
		$this->throwing = false;
	}
}


class ThrowingOnClone extends Component
{
	public function __clone()
	{
		throw new RuntimeException('clone failed');
	}
}


test('failed detach leaves the component in its place in the container', function () {
	$container = new Container;
	$container->addComponent(new Container, 'first');
	$container->addComponent($child = new ThrowingOnDetach, 'child');
	$container->addComponent(new Container, 'last');
	$child->setupMonitoring();

	Assert::exception(
		fn() => $container->removeComponent($child),
		RuntimeException::class,
		'detach failed',
	);

	// container and component must not disagree about the parentage
	Assert::same($child, $container->getComponent('child'));
	Assert::same($container, $child->getParent());
	Assert::same(['first', 'child', 'last'], array_keys($container->getComponents()));

	// and the component is still removable once the listener stops throwing
	$child->stopThrowing();
	$container->removeComponent($child);
	Assert::same(['first', 'last'], array_keys($container->getComponents()));
	Assert::null($child->getParent());
});


test('detached listener sees the component still in the container', function () {
	$container = new Container;
	$container->addComponent($child = new class extends Component {
	}, 'child');
	$child->monitor(Container::class, detached: function (Container $parent) use ($child, &$seen): void {
		$seen = $parent->getComponent('child', throw: false) === $child;
	});

	$container->removeComponent($child);
	Assert::true($seen);
	Assert::same([], $container->getComponents());
});


test('listener that replaces the removed component keeps the replacement', function () {
	$container = new Container;
	$container->addComponent($child = new class extends Component {
	}, 'child');
	$replacement = new class extends Component {
	};
	$child->monitor(Container::class, detached: function () use ($container, $child, $replacement): void {
		if ($child->getParent()) {
			$container->removeComponent($child);
			$container->addComponent($replacement, 'child');
		}
	});

	$container->removeComponent($child);

	Assert::null($child->getParent());
	Assert::same($replacement, $container->getComponent('child'));
	Assert::same($container, $replacement->getParent());
});


test('failed clone does not leave the original container in cloning state', function () {
	$sub = new Container;
	$sub->addComponent($good = new Container, 'good');
	$sub->addComponent(new ThrowingOnClone, 'bad');

	Assert::exception(
		fn() => clone $sub,
		RuntimeException::class,
		'clone failed',
	);

	// cloning a child of $sub now must detach it, not attach it to the discarded clone
	$dolly = clone $good;
	Assert::null($dolly->getParent());
});
