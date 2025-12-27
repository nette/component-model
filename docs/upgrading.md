# Upgrading nette/component-model

## 4.0.0

- **Removed** `Nette\ComponentModel\Component::$name` (deprecated since 3.2.0): call `Nette\ComponentModel\Component::getName()`; the magic property disappeared together with `Nette\SmartObject` (c07bbeeb, 71321c9d)
- **Removed** `Nette\ComponentModel\Component::$parent` (deprecated since 3.2.0): call `Nette\ComponentModel\Component::getParent()`; the magic property disappeared together with `Nette\SmartObject` (c07bbeeb, 71321c9d)
- **Removed** `Nette\ComponentModel\Container::$components` (deprecated since 3.2.0): call `Nette\ComponentModel\Container::getComponents()`; the magic property disappeared together with `Nette\SmartObject` (c07bbeeb, 71321c9d)
- **Removed** `Nette\ComponentModel\Component::attached()` (deprecated since 3.0.0): pass an attached callback as the second argument of `Nette\ComponentModel\Component::monitor()`; affects subclasses overriding it (71321c9d)
- **Removed** `Nette\ComponentModel\Component::detached()` (deprecated since 3.0.0): pass a detached callback as the third argument of `Nette\ComponentModel\Component::monitor()`; affects subclasses overriding it (71321c9d)
- **Removed** `Nette\ComponentModel\IComponent::NAME_SEPARATOR` (deprecated since 3.0.3): use `Nette\ComponentModel\IComponent::NameSeparator` (71321c9d)
- **Removed** `$filterType` of `Nette\ComponentModel\Container::getComponents()`: filter the returned array yourself; passing it throws `Nette\DeprecatedException` (c40aa8ed)
- **Replaced** `$deep` of `Nette\ComponentModel\Container::getComponents()` with `Nette\ComponentModel\Container::getComponentTree()`: passing it throws `Nette\DeprecatedException` (c40aa8ed)
- **Signature** `Nette\ComponentModel\Container::getComponents()`: the return type is now `array`; previously `iterable` (c40aa8ed)
- **Signature** `Nette\ComponentModel\IContainer::getComponents()`: the return type is now `array`; previously `iterable`; affects implementations (c40aa8ed)
- **Signature** `Nette\ComponentModel\Container::addComponent()`: the return type `static` is now declared; affects subclasses overriding it (7ddd4f98)
- **Signature** `Nette\ComponentModel\IContainer::addComponent()`: the return type `static` is now declared; affects implementations (7ddd4f98)
- **Changed** `Nette\ComponentModel\Component::monitor()` now throws `Nette\InvalidStateException` when called with `$type` alone; previously it fell back to the `Nette\ComponentModel\Component::attached()` and `Nette\ComponentModel\Component::detached()` methods; pass at least one callback (71321c9d)
- **Changed** `Nette\ComponentModel\Component` no longer uses `Nette\SmartObject`; reading an undeclared property or calling an undeclared method on a component no longer throws `Nette\MemberAccessException` with a hint but follows plain PHP behavior; add `use Nette\SmartObject;` to your own subclass to keep it (c07bbeeb)

## 3.2.0

- **Replaced** `Nette\ComponentModel\Component::$name` with `Nette\ComponentModel\Component::getName()`: read the value through the getter; using the old name triggers E_USER_DEPRECATED (31b6c89b)
- **Replaced** `Nette\ComponentModel\Component::$parent` with `Nette\ComponentModel\Component::getParent()`: read the value through the getter; using the old name triggers E_USER_DEPRECATED (31b6c89b)
- **Replaced** `Nette\ComponentModel\Container::$components` with `Nette\ComponentModel\Container::getComponents()`: read the value through the getter; using the old name triggers E_USER_DEPRECATED (31b6c89b)
- **Deprecated** `Nette\ComponentModel\Component::attached()` (deprecated since 3.0.0): pass an attached callback as the second argument of `Nette\ComponentModel\Component::monitor()`; calling `Nette\ComponentModel\Component::monitor()` with `$type` alone triggers E_USER_DEPRECATED (cceb117d)
- **Deprecated** `Nette\ComponentModel\Component::detached()` (deprecated since 3.0.0): pass a detached callback as the third argument of `Nette\ComponentModel\Component::monitor()`; calling `Nette\ComponentModel\Component::monitor()` with `$type` alone triggers E_USER_DEPRECATED (cceb117d)
- **Deprecated** `Nette\ComponentModel\IComponent::NAME_SEPARATOR` (alias since 3.0.3): use `Nette\ComponentModel\IComponent::NameSeparator`; the constant now carries `#[\Deprecated]`, so using the old name triggers E_USER_DEPRECATED on PHP 8.4 and later (23ad9cf9)

## 3.1.2

- **Removed** `Nette\ComponentModel\Component::__wakeup()`: unserializing a component no longer throws `Nette\NotImplementedException` (f8debd48)
- **Renamed** `Nette\ComponentModel\Component::__sleep()` → `Nette\ComponentModel\Component::__serialize()`; the old name was removed (f8debd48)

## 3.1.0

- **Signature** `Nette\ComponentModel\Container::getComponents()`: the parameters `$deep` and `$filterType` were dropped from the declaration; calls that pass them still work through `func_get_args()`, but they are silently deprecated (4e0946a7)
- **Signature** `Nette\ComponentModel\Container::getComponents()`: the return type is now `iterable`; previously `\Iterator` (7f613eed)
- **Signature** `Nette\ComponentModel\IContainer::getComponents()`: the return type is now `iterable`; previously `\Iterator`; affects implementations (7f613eed)
- **Signature** `Nette\ComponentModel\IComponent::setParent()`: the return type `static` is now declared; affects implementations (fa48b499)
- **Changed** `Nette\ComponentModel\Container::getComponents()` called without arguments now returns an array of the immediate children; previously it returned a `Nette\ComponentModel\RecursiveComponentIterator`; code calling `rewind()`, `valid()` or `next()` on the result or type-hinting `\Iterator` must be changed, and the same applies to the magic property `Nette\ComponentModel\Container::$components` (7f613eed)

## 3.0.3

- **Renamed** `Nette\ComponentModel\IComponent::NAME_SEPARATOR` → `Nette\ComponentModel\IComponent::NameSeparator`; the old name still works as an alias (80350e59)

## 3.0.1

- **Changed** `Nette\ComponentModel\Container::addComponent()` now throws `Nette\InvalidStateException` when `$name` is null and the component has no name of its own; previously it threw `Nette\InvalidArgumentException`; affects code catching it (66409cf5)

## 3.0.0

- **Removed** `Nette\ComponentModel\Component::__construct()` (deprecated since 2.4.0): the class declares no constructor at all; previously it accepted `$parent` and `$name` through `func_get_args()`; attach the component with `Nette\ComponentModel\IContainer::addComponent()` and drop any `parent::__construct()` call from a subclass constructor (c5f9f0d3)
- **Deprecated** `Nette\ComponentModel\Component::attached()`: pass an attached callback as the second argument of `Nette\ComponentModel\Component::monitor()`; the old name is silently deprecated (857a493a)
- **Deprecated** `Nette\ComponentModel\Component::detached()`: pass a detached callback as the third argument of `Nette\ComponentModel\Component::monitor()`; the old name is silently deprecated (857a493a)
- **Signature** `Nette\ComponentModel\Container::addComponent()`: `$name` is typed `?string` and `$insertBefore` is typed `string`; passing an integer name throws a `TypeError`, previously it was accepted and cast (47dab8f3)
- **Signature** `Nette\ComponentModel\Container::getComponent()`: `$name` is typed `string`; passing an integer throws a `TypeError`, previously it was accepted and cast; array access on a container still casts integer keys to string (47dab8f3)
- **Signature** `Nette\ComponentModel\IContainer::addComponent()`: `$name` is typed `?string`; affects implementations (47dab8f3, 1a6d34d9)
- **Signature** `Nette\ComponentModel\IContainer::getComponent()`: `$name` is typed `string`; affects implementations (47dab8f3)
- **Signature** `Nette\ComponentModel\IContainer::getComponents()`: the parameters `$deep` and `$filterType` were dropped and the return type `\Iterator` was declared, while `Nette\ComponentModel\Container::getComponents()` keeps them; affects implementations (f69df2ca, 1a6d34d9)
- **Signature** `Nette\ComponentModel\IComponent::setParent()`: `$parent` has no default value any more and `$name` is typed `string`; affects implementations and calls made without arguments (f69df2ca)
- **Signature** native parameter and return types were added throughout the package; affects subclasses overriding these methods and implementations of the interfaces: `Nette\ComponentModel\Container::createComponent()`, `Nette\ComponentModel\Container::validateChildComponent()`, `Nette\ComponentModel\Container::removeComponent()`, `Nette\ComponentModel\Component::validateParent()`, `Nette\ComponentModel\Component::attached()`, `Nette\ComponentModel\Component::detached()`, `Nette\ComponentModel\Component::lookup()`, `Nette\ComponentModel\Component::lookupPath()`, `Nette\ComponentModel\Component::monitor()`, `Nette\ComponentModel\Component::unmonitor()`, `Nette\ComponentModel\IComponent::getName()`, `Nette\ComponentModel\IComponent::getParent()`, `Nette\ComponentModel\IContainer::removeComponent()` (f69df2ca)
- **Signature** the following members are now `final`; affects subclasses overriding them: `Nette\ComponentModel\Component::getName()`, `Nette\ComponentModel\Component::getParent()`, `Nette\ComponentModel\Component::lookup()`, `Nette\ComponentModel\Component::lookupPath()`, `Nette\ComponentModel\Component::monitor()`, `Nette\ComponentModel\Component::unmonitor()`, `Nette\ComponentModel\Component::__sleep()`, `Nette\ComponentModel\Component::__wakeup()`, `Nette\ComponentModel\Container::getComponent()`, `Nette\ComponentModel\Container::getComponents()` (2f050608)
- **Changed** `Nette\ComponentModel\Container::getComponents()` returns the descendants self-first for any truthy `$deep`; previously a negative `$deep` returned them child-first (d1b01639)
- **Changed** `Nette\ComponentModel\Container::getComponent()` now requires the name to be a non-empty alphanumeric string and throws `Nette\InvalidArgumentException` otherwise; previously only an empty name was rejected and any other unknown name was passed on to `Nette\ComponentModel\Container::createComponent()`; the message of the exception changed as well (cfcf2b2f)
