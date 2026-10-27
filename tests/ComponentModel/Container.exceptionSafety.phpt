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


class Root extends Container
{
}


class Listener extends Component
{
	public array $log = [];


	public function __construct(?string $throwOn = null)
	{
		$this->monitor(
			Root::class,
			function () use ($throwOn): void {
				$this->log[] = 'attached';
				$this->lookup(Root::class); // fills the lookup cache, which a rollback must drop
				if ($throwOn === 'attached') {
					throw new RuntimeException('attach failed');
				}
			},
			function () use ($throwOn): void {
				$this->log[] = 'detached';
				if ($throwOn === 'detached') {
					throw new RuntimeException('detach failed');
				}
			},
		);
	}
}


test('failed attach rolls the component back out of the tree', function () {
	$sub = new Container;
	$sub->addComponent($listener = new Listener, 'listener');
	$sub->addComponent($veto = new Listener('attached'), 'veto');
	$root = new Root;

	Assert::exception(
		fn() => $root->addComponent($sub, 'sub'),
		RuntimeException::class,
		'attach failed',
	);

	Assert::null($sub->getParent());
	Assert::null($sub->getName());
	Assert::same([], $root->getComponents());
	Assert::null($listener->lookup(Root::class, throw: false));
	Assert::same(['attached', 'detached'], $listener->log);

	// the vetoing listener stays registered
	Assert::exception(
		fn() => $root->addComponent($sub, 'sub'),
		RuntimeException::class,
		'attach failed',
	);
	Assert::same(['attached', 'detached', 'attached', 'detached'], $veto->log);
});


test('failed detach rolls the component back into the tree', function () {
	$root = new Root;
	$root->addComponent($sub = new Container, 'sub');
	$sub->addComponent($listener = new Listener, 'listener');
	$sub->addComponent(new Listener('detached'), 'veto');

	Assert::exception(
		fn() => $root->removeComponent($sub),
		RuntimeException::class,
		'detach failed',
	);

	Assert::same($root, $sub->getParent());
	Assert::same(['sub'], array_keys($root->getComponents()));
	Assert::same($root, $listener->lookup(Root::class));
	Assert::same(['attached', 'detached', 'attached'], $listener->log);
});


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


test('component refused under a second name stays under the first one only', function () {
	$container = new Container;
	$container->addComponent($component = new class extends Component {
	}, 'original');

	Assert::exception(
		fn() => $container->addComponent($component, 'alias'),
		Nette\InvalidStateException::class,
		"Component 'original' already has a parent.",
	);

	Assert::same(['original'], array_keys($container->getComponents()));
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


test('rollback goes to the end even if a listener throws during it', function () {
	$sub = new Container;
	$sub->addComponent($first = new Listener('detached'), 'first');
	$sub->addComponent($second = new Listener, 'second');
	$sub->addComponent(new Listener('attached'), 'veto');
	$root = new Root;

	// the veto is what gets thrown, not the failure of the rollback
	Assert::exception(
		fn() => $root->addComponent($sub, 'sub'),
		RuntimeException::class,
		'attach failed',
	);

	Assert::null($sub->getParent());
	Assert::null($second->lookup(Root::class, throw: false));
	Assert::same(['attached', 'detached'], $first->log);
	Assert::same(['attached', 'detached'], $second->log);
});


test('listener that moves the component and then throws keeps the move', function () {
	$root = new Root;
	$other = new Root;
	$moved = false;
	$component = new class extends Component {
	};
	$component->monitor(Root::class, function () use ($component, $other, &$moved): void {
		if (!$moved) {
			$moved = true;
			$component->getParent()->removeComponent($component);
			$other->addComponent($component, 'moved');
			throw new RuntimeException('attach failed');
		}
	});

	Assert::exception(
		fn() => $root->addComponent($component, 'component'),
		RuntimeException::class,
		'attach failed',
	);

	Assert::same($other, $component->getParent());
	Assert::same('moved', $component->getName());
	Assert::same(['moved'], array_keys($other->getComponents()));
	Assert::same([], $root->getComponents());
	Assert::same($other, $component->lookup(Root::class));
});


test('listener that puts the component back and then throws keeps it', function () {
	$root = new Root;
	$done = false;
	$component = new class extends Component {
	};
	$component->monitor(Root::class, function () use ($component, $root, &$done): void {
		if (!$done) {
			$done = true;
			$root->removeComponent($component);
			$root->addComponent($component, 'component');
			throw new RuntimeException('attach failed');
		}
	});

	Assert::exception(
		fn() => $root->addComponent($component, 'component'),
		RuntimeException::class,
		'attach failed',
	);

	Assert::same($root, $component->getParent());
	Assert::same(['component'], array_keys($root->getComponents()));
});


test('handler of the rollback that moves the component keeps the move', function () {
	$root = new Root;
	$other = new Root;
	$component = new class extends Component {
	};
	$component->monitor(
		Root::class,
		fn() => throw new RuntimeException('attach failed'),
		function () use ($component, $other): void {
			if ($component->getParent() !== $other) {
				$component->getParent()->removeComponent($component);
				$other->addComponent($component, 'moved');
			}
		},
	);

	Assert::exception(
		fn() => $root->addComponent($component, 'component'),
		RuntimeException::class,
		'attach failed',
	);

	Assert::same($other, $component->getParent());
	Assert::same('moved', $component->getName());
	Assert::same(['moved'], array_keys($other->getComponents()));
});


test('rollback calls every handler of a type even if one of them throws', function () {
	$sub = new Container;
	$sub->addComponent($component = new class extends Component {
	}, 'component');
	$log = [];
	$component->monitor(Root::class, detached: function () use (&$log): void {
		$log[] = 'first';
		throw new LogicException('cleanup failed');
	});
	$component->monitor(Root::class, detached: function () use (&$log): void {
		$log[] = 'second';
	});
	$sub->addComponent(new Listener('attached'), 'veto');

	Assert::exception(
		fn() => (new Root)->addComponent($sub, 'sub'),
		RuntimeException::class,
		'attach failed',
	);

	Assert::same(['first', 'second'], $log);
});


test('veto still calls the other handlers of the type, so the rollback balances all of them', function () {
	$log = [];
	$component = new class extends Component {
	};
	$component->monitor(
		Root::class,
		function () use (&$log): void {
			$log[] = 'first attached';
			throw new RuntimeException('attach failed');
		},
		function () use (&$log): void { $log[] = 'first detached'; },
	);
	$component->monitor(
		Root::class,
		function () use (&$log): void { $log[] = 'second attached'; },
		function () use (&$log): void { $log[] = 'second detached'; },
	);

	Assert::exception(
		fn() => (new Root)->addComponent($component, 'component'),
		RuntimeException::class,
		'attach failed',
	);

	Assert::same(['first attached', 'second attached', 'first detached', 'second detached'], $log);
});


test('handler that moves the component and then throws still gets the others told where it is', function () {
	$log = [];
	$root = new Root;
	$other = new Root;
	$component = new class extends Component {
	};
	$component->monitor(Root::class, function () use ($component, $other): void {
		if ($component->getParent() !== $other) {
			$component->getParent()->removeComponent($component);
			$other->addComponent($component, 'moved');
			throw new RuntimeException('attach failed');
		}
	});
	$component->monitor(
		Root::class,
		function (Root $r) use (&$log, $root): void { $log[] = 'attached ' . ($r === $root ? 'root' : 'other'); },
		function (Root $r) use (&$log, $root): void { $log[] = 'detached ' . ($r === $root ? 'root' : 'other'); },
	);

	Assert::exception(
		fn() => $root->addComponent($component, 'component'),
		RuntimeException::class,
		'attach failed',
	);

	Assert::same($other, $component->getParent());
	Assert::same(['attached root', 'detached root', 'attached other'], $log);
});
