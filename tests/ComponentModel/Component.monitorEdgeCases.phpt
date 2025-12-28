<?php declare(strict_types=1);

/**
 * Test: Nette\ComponentModel\Component monitor edge cases
 * Tests behavior when components are removed during attachment processing.
 */

use Nette\ComponentModel\Component;
use Nette\ComponentModel\Container;
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
	Assert::true($child->attachedCalled); // problem: child listener is called although parent removed it first
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
		Assert::true($child->attachedCalled); // problem: child listener is called although parent removed it first
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
	Assert::true($child2->attachedCalled); // problem: child2 listener is called although child1 removed it first
	Assert::same(1, count($container->getComponents()));
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


// This test verifies that $processed tracking in refreshMonitors works correctly.
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
			private Container $target,
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

	Assert::same(2, $callCount); // problem: callback must be called exactly once
	Assert::same($secondContainer, $mover->getParent());
});
