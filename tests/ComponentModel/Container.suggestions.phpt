<?php

/**
 * Test: Nette\ComponentModel\Container suggestions.
 */

declare(strict_types=1);

use Nette\ComponentModel\Container;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


class TestContainer extends Container
{
	public function createComponentPublic()
	{
	}


	public static function createComponentPublicStatic()
	{
	}


	protected function createComponentProtected()
	{
	}


	private function createComponentPrivate()
	{
	}
}


$cont = new TestContainer;
$cont->addComponent(new TestContainer, 'form');
$cont->addComponent(new TestContainer, '0');

Assert::exception(
	fn() => $cont->getComponent('from'),
	Nette\InvalidArgumentException::class,
	"Component with name 'from' does not exist, did you mean 'form'?",
);

Assert::exception(
	fn() => $cont->getComponent('Public'),
	Nette\InvalidArgumentException::class,
	"Component with name 'Public' does not exist, did you mean 'public'?",
);

Assert::exception(
	fn() => $cont->getComponent('PublicStatic'),
	Nette\InvalidArgumentException::class,
	"Component with name 'PublicStatic' does not exist, did you mean 'publicStatic'?",
);

Assert::exception(
	fn() => $cont->getComponent('Protected'),
	Nette\InvalidArgumentException::class,
	"Component with name 'Protected' does not exist, did you mean 'protected'?",
);

Assert::exception(
	fn() => $cont->getComponent('Private'),
	Nette\InvalidArgumentException::class,
	"Component with name 'Private' does not exist.",
);
