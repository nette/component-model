<?php declare(strict_types=1);

/**
 * Test: Nette\ComponentModel\Component monitor edge cases
 * Tests behavior when components are removed during attachment processing.
 */

use Nette\ComponentModel\{Component, Container};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class TestRoot extends Container
{
}


class TestParent extends Container
{
	public bool $attachedCalled = false;


	public function setupMonitoring(): void
	{
		$this->monitor(TestRoot::class, function (): void {
			$this->attachedCalled = true;

			foreach ($this->getComponents() as $c) {
				$this->removeComponent($c);
			}
		});
	}
}


class TestChild extends Component
{
	public bool $attachedCalled = false;
	private ?TestChild $siblingToRemove = null;


	public function setSiblingToRemove(?self $sibling): void
	{
		$this->siblingToRemove = $sibling;
	}


	public function setupMonitoring(): void
	{
		$this->monitor(TestRoot::class, function (): void {
			$this->attachedCalled = true;

			if ($this->siblingToRemove && ($parent = $this->getParent()) instanceof Container) {
				$parent->removeComponent($this->siblingToRemove);
			}
		});
	}
}


test('parent removes children before their listeners run', function () {
	$root = new TestRoot;
	$parent = new TestParent;
	$parent->setupMonitoring();

	$childContainer = new Container;
	$child = new TestChild;
	$child->setupMonitoring();
	$childContainer->addComponent($child, 'child');
	$parent->addComponent($childContainer, 'item0');

	Assert::same(1, count($parent->getComponents()));

	$root->addComponent($parent, 'parent');

	Assert::true($parent->attachedCalled);
	Assert::false($child->attachedCalled, 'Child listener NOT called - parent removed it first');
	Assert::same(0, count($parent->getComponents()));
});


test('multiple nested children removed by parent', function () {
	$root = new TestRoot;
	$parent = new TestParent;
	$parent->setupMonitoring();

	$children = [];
	for ($i = 0; $i < 3; $i++) {
		$container = new Container;
		$child = new TestChild;
		$child->setupMonitoring();
		$children[] = $child;
		$container->addComponent($child, "child$i");
		$parent->addComponent($container, "item$i");
	}

	Assert::same(3, count($parent->getComponents()));

	$root->addComponent($parent, 'parent');

	foreach ($children as $i => $child) {
		Assert::false($child->attachedCalled, "Child $i listener NOT called - parent removed it first");
	}

	Assert::same(0, count($parent->getComponents()));
});


test('child removes sibling before its listener runs', function () {
	$root = new TestRoot;
	$container = new Container;

	$child1 = new TestChild;
	$child2 = new TestChild;

	$child1->setSiblingToRemove($child2);

	$child1->setupMonitoring();
	$child2->setupMonitoring();

	$container->addComponent($child1, 'child1');
	$container->addComponent($child2, 'child2');

	Assert::same(2, count($container->getComponents()));

	$root->addComponent($container, 'container');

	Assert::true($child1->attachedCalled);
	Assert::false($child2->attachedCalled, 'child2 listener NOT called - child1 removed it first');
	Assert::same(1, count($container->getComponents()));
});


test('reentry protection - component processed only once', function () {
	$root = new TestRoot;
	$callCount = 0;

	$component = new class ($callCount) extends Component {
		public function __construct(
			private int &$callCount,
		) {
		}


		public function setupMonitoring(): void
		{
			$this->monitor(TestRoot::class, function (): void {
				$this->callCount++;

				$parent = $this->getParent();
				if ($parent) {
					$parent->removeComponent($this);
					$parent->addComponent($this, $this->getName());
				}
			});
		}
	};

	$component->setupMonitoring();
	$root->addComponent($component, 'comp');

	Assert::same(1, $callCount, 'Component processed exactly once');
});


test('deeply nested removal - depth-first traversal', function () {
	$root = new TestRoot;
	$level1 = new Container;
	$level2 = new Container;
	$level3 = new Container;

	$deepChild = new TestChild;
	$deepChild->setupMonitoring();

	$level3->addComponent($deepChild, 'deep');
	$level2->addComponent($level3, 'level3');
	$level1->addComponent($level2, 'level2');

	$sibling = new class extends Component {
		public function setupMonitoring(): void
		{
			$this->monitor(TestRoot::class, function (): void {
				$parent = $this->getParent();
				if ($parent instanceof Container) {
					$level2 = $parent->getComponent('level2', throw: false);
					if ($level2) {
						$parent->removeComponent($level2);
					}
				}
			});
		}
	};
	$sibling->setupMonitoring();

	$level1->addComponent($sibling, 'sibling');

	$root->addComponent($level1, 'level1');

	// deepChild listener IS called - processed before sibling removed ancestor
	Assert::true($deepChild->attachedCalled);
});


// This test verifies that $processed tracking in attachMonitors works correctly.
// Without it the mover component would be processed twice - once when first visited,
// and again when secondContainer is traversed (since mover moved there during callback).
test('component moved to later-visited container is not reprocessed', function () {
	$root = new TestRoot;
	$callCount = 0;

	$firstContainer = new Container;
	$secondContainer = new Container;

	$mover = new class ($callCount, $secondContainer) extends Component {
		public function __construct(
			private int &$callCount,
			private readonly Container $target,
		) {
		}


		public function setupMonitoring(): void
		{
			$this->monitor(TestRoot::class, function () {
				$this->callCount++;

				// Move self from current parent to target container
				$parent = $this->getParent();
				if ($parent instanceof Container && $parent !== $this->target) {
					$parent->removeComponent($this);
					$this->target->addComponent($this, 'moved');
				}
			});
		}
	};

	$mover->setupMonitoring();
	$firstContainer->addComponent($mover, 'mover');

	$parent = new Container;
	$parent->addComponent($firstContainer, 'first');
	$parent->addComponent($secondContainer, 'second'); // visited AFTER first

	// When parent is attached to root:
	// 1. Traversal visits firstContainer, then mover
	// 2. mover's callback moves it to secondContainer
	// 3. Traversal continues to secondContainer (which now contains mover)
	// 4. Without $processed tracking, mover would be processed again
	$root->addComponent($parent, 'parent');

	Assert::same(1, $callCount, 'Callback must be called exactly once');
	Assert::same($secondContainer, $mover->getParent());
});


test('component moved to another tree by its own handler', function () {
	$log = [];
	$root = new TestRoot;
	$other = new TestRoot;
	$mover = new class extends Component {
	};
	$mover->monitor(
		TestRoot::class,
		function (TestRoot $r) use (&$log, $mover, $other): void {
			$log[] = 'attached ' . spl_object_id($r);
			if ($mover->getParent() !== $other) {
				$mover->getParent()->removeComponent($mover);
				$other->addComponent($mover, 'moved');
			}
		},
		function (TestRoot $r) use (&$log): void {
			$log[] = 'detached ' . spl_object_id($r);
		},
	);

	$root->addComponent($mover, 'mover');

	// the handlers are told about the change they made once they return, so the pairs match
	$r = spl_object_id($root);
	$o = spl_object_id($other);
	Assert::same(["attached $r", "detached $r", "attached $o"], $log);
	Assert::same($other, $mover->getParent());
	Assert::same($other, $mover->lookup(TestRoot::class));
	Assert::same([], $root->getComponents());

	$other->removeComponent($mover);
	Assert::same(["attached $r", "detached $r", "attached $o", "detached $o"], $log);
});


test('other handlers of the type are told where the moved component has ended up', function () {
	foreach (['attached', 'detached'] as $moveOn) {
		$log = [];
		$root = new TestRoot;
		$other = new TestRoot;
		$root->addComponent($box = new Container, 'box');
		$component = new class extends Component {
		};
		$move = function () use ($component, $other): void {
			if ($component->getParent() && $component->lookup(TestRoot::class, throw: false) !== $other) {
				$component->getParent()->removeComponent($component);
				$other->addComponent($component, 'moved');
			}
		};
		$second = function (string $event) use (&$log, $root): Closure {
			return function (TestRoot $r) use (&$log, $root, $event): void {
				$log[] = $event . ' ' . ($r === $root ? 'root' : 'other');
			};
		};
		$component->monitor(
			TestRoot::class,
			fn() => $moveOn === 'attached' ? $move() : null,
			fn() => $moveOn === 'detached' ? $move() : null,
		);
		$component->monitor(TestRoot::class, $second('attached'), $second('detached'));

		$box->addComponent($component, 'component');
		if ($moveOn === 'detached') {
			$box->removeComponent($component);
		}

		Assert::same($other, $component->lookup(TestRoot::class), $moveOn);
		Assert::same(['attached root', 'detached root', 'attached other'], $log, $moveOn);
	}
});


class TestMiddle extends Container
{
}


test('detached handler cuts off an ancestor beyond the cut', function () {
	$log = [];
	$root = new TestRoot;
	$root->addComponent($middle = new TestMiddle, 'middle');
	$middle->addComponent($grand = new Container, 'grand');
	$grand->addComponent($parent = new Container, 'parent');
	$parent->addComponent($child = new class extends Component {
	}, 'child');

	$child->monitor(TestRoot::class, detached: function () use (&$log, $middle, $grand): void {
		$log[] = 'root';
		$middle->removeComponent($grand);
	});
	$child->monitor(TestMiddle::class, detached: function () use (&$log): void {
		$log[] = 'middle';
	});

	$grand->removeComponent($parent);

	// the nested removal has notified the middle monitor already, the outer walk must not do it again
	Assert::same(['root', 'middle'], $log);
	Assert::null($child->lookup(TestRoot::class, throw: false));
});


test('detached handler puts its component back into the same container', function () {
	foreach (['again', 'child'] as $newName) {
		$root = new TestRoot;
		$root->addComponent($child = new class extends Component {
		}, 'child');
		$done = false;
		$child->monitor(TestRoot::class, detached: function () use ($root, $child, $newName, &$done): void {
			if (!$done) {
				$done = true;
				$root->removeComponent($child);
				$root->addComponent($child, $newName);
			}
		});

		$root->removeComponent($child);

		// the same parent again is a change of its own, the outer removal must not undo it
		Assert::same($root, $child->getParent());
		Assert::same([$newName], array_keys($root->getComponents()));
	}
});


test('child added by a detached handler is detached along', function () {
	$log = [];
	$root = new TestRoot;
	$root->addComponent($parent = new Container, 'parent');
	$parent->addComponent($first = new class extends Component {
	}, 'first');
	$added = new class extends Component {
	};
	$added->monitor(
		TestRoot::class,
		function () use (&$log): void { $log[] = 'attached'; },
		function () use (&$log): void { $log[] = 'detached'; },
	);
	$first->monitor(TestRoot::class, detached: function () use ($parent, $added): void {
		if (!$added->getParent()) {
			$parent->addComponent($added, 'added');
		}
	});

	$root->removeComponent($parent);
	Assert::same(['attached', 'detached'], $log);
	Assert::null($added->lookup(TestRoot::class, throw: false));

	$root->addComponent($parent, 'parent');
	Assert::same(['attached', 'detached', 'attached'], $log);

	$parent->removeComponent($added);
	Assert::same(['attached', 'detached', 'attached', 'detached'], $log);
});


test('child added by the detached handler of the container being cut off is detached along', function () {
	$log = [];
	$root = new TestRoot;
	$root->addComponent($parent = new Container, 'parent');
	$added = new class extends Component {
	};
	$added->monitor(
		TestRoot::class,
		function () use (&$log): void { $log[] = 'attached'; },
		function () use (&$log): void { $log[] = 'detached'; },
	);
	$parent->monitor(TestRoot::class, detached: function () use ($parent, $added): void {
		if (!$added->getParent()) {
			$parent->addComponent($added, 'added');
		}
	});

	$root->removeComponent($parent);
	Assert::same(['attached', 'detached'], $log);
	Assert::null($added->lookup(TestRoot::class, throw: false));

	$root->addComponent($parent, 'parent');
	$parent->removeComponent($added);
	Assert::same(['attached', 'detached', 'attached', 'detached'], $log);
});


test('attached handler that cuts off an ancestor of its component is told detached', function () {
	$log = [];
	$root = new TestRoot;
	$root->addComponent($box = new Container, 'box');
	$component = new class extends Component {
	};
	$component->monitor(
		TestRoot::class,
		function () use (&$log, $root, $box): void {
			$log[] = 'attached';
			if ($box->getParent()) {
				$root->removeComponent($box);
			}
		},
		function () use (&$log): void { $log[] = 'detached'; },
	);

	$box->addComponent($component, 'component');

	Assert::null($component->lookup(TestRoot::class, throw: false));
	Assert::same(['attached', 'detached'], $log);
});


test('sibling that puts back an already detached child makes it detach again', function () {
	$log = [];
	$root = new TestRoot;
	$root->addComponent($parent = new Container, 'parent');
	$parent->addComponent($first = new class extends Component {
	}, 'first');
	$parent->addComponent($second = new class extends Component {
	}, 'second');
	$first->monitor(
		TestRoot::class,
		function () use (&$log): void { $log[] = 'attached'; },
		function () use (&$log): void { $log[] = 'detached'; },
	);
	$done = false;
	$second->monitor(TestRoot::class, detached: function () use ($parent, $first, &$done): void {
		if (!$done) {
			$done = true;
			$parent->removeComponent($first);
			$parent->addComponent($first, 'first');
		}
	});

	$root->removeComponent($parent);

	Assert::null($first->lookup(TestRoot::class, throw: false));
	Assert::same(['attached', 'detached', 'attached', 'detached'], $log);
	$parent->removeComponent($first); // must not fail on a stale state
});
