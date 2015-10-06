<?php

/**
 * Test: Nette\ComponentModel\Container suggestions.
 */

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

Assert::exception(function () use ($cont) {
	$comp = $cont->getComponent('from');
}, Nette\InvalidArgumentException::class, "Component with name 'from' does not exist, did you mean 'form'?");

Assert::exception(function () use ($cont) {
	$comp = $cont->getComponent('Public');
}, Nette\InvalidArgumentException::class, "Component with name 'Public' does not exist, did you mean 'public'?");

Assert::exception(function () use ($cont) {
	$comp = $cont->getComponent('PublicStatic');
}, Nette\InvalidArgumentException::class, "Component with name 'PublicStatic' does not exist, did you mean 'publicStatic'?");

Assert::exception(function () use ($cont) {
	$comp = $cont->getComponent('Protected');
}, Nette\InvalidArgumentException::class, "Component with name 'Protected' does not exist, did you mean 'protected'?");

Assert::exception(function () use ($cont) { // suggest only non-private methods
	$comp = $cont->getComponent('Private');
}, Nette\InvalidArgumentException::class, "Component with name 'Private' does not exist.");
