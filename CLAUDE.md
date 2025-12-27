# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is **nette/component-model**, a foundational library providing the component architecture for the Nette Framework. It implements a hierarchical component system where components can be nested within containers, monitored for attach/detach events, and accessed through a path-based lookup system.

**Requirements:** PHP 8.2 - 8.5

## Essential Commands

### Testing
```bash
# Run all tests
composer tester
# or directly:
vendor/bin/tester tests -s

# Run specific test file
php tests/ComponentModel/Container.getComponents.phpt

# Run tests in specific directory
vendor/bin/tester tests/ComponentModel -s
```

### Static Analysis
```bash
# Run PHPStan (level 8)
composer phpstan
# or directly:
vendor/bin/phpstan analyse
```

## Architecture Overview

### Core Classes and Interfaces

The library consists of 5 primary files in `src/ComponentModel/`:

1. **IComponent** - Base interface defining core component functionality
   - `getName()`, `getParent()`, `setParent()`
   - Defines `NameSeparator` constant (`-`) for component path construction

2. **IContainer** - Interface for components that can contain child components
   - `addComponent()`, `removeComponent()`, `getComponent()`, `getComponents()`
   - Generic typed: `@template T of IComponent`

3. **Component** - Abstract base class implementing IComponent
   - Provides parent-child relationship management
   - Implements **monitoring system** for ancestor attach/detach events
   - `lookup()` - finds closest ancestor of specified type
   - `lookupPath()` - returns path from ancestor to this component
   - `monitor()` - registers callbacks for when component is attached/detached to ancestor of specific type
   - Uses SmartObject trait from nette/utils

4. **Container** - Concrete implementation of IContainer extending Component
   - Manages child component collection (`$components` array)
   - Implements **factory pattern**: `createComponent($name)` delegates to `createComponent<Name>()` methods
   - `getComponent()` - retrieves or auto-creates components via factory methods
   - `getComponents()` - returns immediate children (deprecated parameters for recursive/filter)
   - `getComponentTree()` - returns flattened depth-first list of all nested components
   - Handles cloning of component trees

5. **ArrayAccess** - Trait providing array-like access to container components
   - `$container['name']` maps to `$container->getComponent('name')`
   - Allows `$container['name'] = $component` syntax

### Key Architectural Concepts

#### Component Hierarchy and Paths
Components form a tree structure. Each component has:
- A parent (IContainer or null for root)
- A name (string or null for root)
- A path constructed with `-` separator (e.g., `form-fieldset-name`)

#### Factory Pattern for Lazy Component Creation
Containers check for `createComponent<Name>()` methods when `getComponent()` is called:
```php
protected function createComponentButton($name): Button
{
    return new Button();
}
// Now $container->getComponent('button') auto-creates if doesn't exist
```

#### Monitoring System
Components can register callbacks to be notified when attached/detached to ancestors:
```php
$component->monitor(Form::class,
    attached: fn(Form $form) => $this->form = $form,
    detached: fn(Form $form) => $this->form = null
);
```
This is internally used for `lookup()` optimization and enables reactive component behavior.

#### Component Validation Hooks
- `validateParent()` - override to restrict what containers a component can be added to
- `validateChildComponent()` - override to restrict what components can be added to a container

### Recent Breaking Changes

The codebase has recently undergone API cleanup:

1. **Container::getComponents()** - Parameters removed
   - Old: `getComponents(bool $recursive, string $filterType)`
   - New: `getComponents()` - returns only immediate children
   - Use `getComponentTree()` instead for recursive traversal

## Test Structure

Tests use **Nette Tester** with `.phpt` extension. Each test file:
- Includes `tests/bootstrap.php` which sets up autoloading
- Uses `test()` function for individual test cases (from Tester 2.5+)
- Uses `Assert` class for assertions
- Often creates minimal test classes (Button, ComponentX) within the test file

Example test pattern:
```php
<?php
declare(strict_types=1);
use Tester\Assert;
require __DIR__ . '/../bootstrap.php';

test('description of what is tested', function () {
    $container = new Container;
    // test code
    Assert::same('expected', $actual);
});
```

The `Notes` class in bootstrap.php is a test helper for collecting messages during component lifecycle events.

## Development Notes

- All files must have `declare(strict_types=1)`
- PHPStan runs at level 8 (strictest)
- CI tests against PHP 8.2, 8.3, 8.4, and 8.5
- The project uses classmap + PSR-4 autoloading for `Nette\` namespace
- Components cannot be serialized (`__serialize()` throws exception)
- Cloning properly deep-clones entire component trees
