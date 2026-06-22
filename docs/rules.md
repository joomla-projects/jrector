# Joomla Rector Rules

Custom [Rector](https://getrector.com/) rules for upgrading Joomla extensions from old Joomla versions up to the Joomla 7.

## Table of Contents

- Joomla 3
  - [ViewAssignRefToPropertyRector](#viewassignreftopropertyrector)
- Joomla 4
  - [JimportRector](#jimportrector)
- Joomla 5
  - [ApplicationInputPropertyRector](#applicationinputpropertyrector)
  - [CurrentUserInterfaceGetUserRector](#currentuserinterfacegetuserrector)
  - [GetDboToGetDatabaseRector](#getdbotogetdatabaserector)
  - [HtmlViewGetToModelGetRector](#htmlviewgettomodelgetrector)
  - [ViewThisTypehintRector](#viewthistypehintrector)
- Joomla 6
  - [HtmlViewExceptionHandlingRector](#htmlviewexceptionhandlingrector)

---

## ViewAssignRefToPropertyRector

**Class:** `Joomla\Rector\Joomla3\ViewAssignRefToPropertyRector`

Replaces `$this->assign('key', $value)` and `$this->assignRef('key', $value)` calls with direct property assignments `$this->key = $value` in Joomla view classes.

In Joomla 3, data was passed to view templates via `assignRef()` — a by-reference assignment inherited from `JView`. In Joomla 4 and later, direct property assignment is the standard pattern.

The rule applies to any class that directly or indirectly extends one of:
- `Joomla\CMS\MVC\View\HtmlView`
- `JViewLegacy`
- `JView`

Direct extension is detected via the AST (no reflection needed). For classes that extend a custom intermediate view class, PHPStan's `ReflectionProvider` walks the full inheritance chain, which requires `autoloadPaths()`.

### Before / After

```php
// Before
class ExampleView extends JView
{
    public function display($tpl = null)
    {
        $items = $this->get('Items');
        $this->assign('items', $items);
        $this->assignRef('user', JFactory::getUser());
        $this->assignRef('state', $this->get('State'));

        parent::display($tpl);
    }
}
```

```php
// After
class ExampleView extends JView
{
    public function display($tpl = null)
    {
        $items = $this->get('Items');
        $this->items = $items;
        $this->user = JFactory::getUser();
        $this->state = $this->get('State');

        parent::display($tpl);
    }
}
```

Both `assign()` and `assignRef()` are handled identically — both become a plain property assignment.

### What is NOT changed

- Classes that do not extend a recognised view base class are skipped entirely.
- `assign()` / `assignRef()` calls whose first argument is not a string literal are left untouched (dynamic key names cannot be safely converted to a property access).

### Configuration

The rule requires no configuration parameters. `autoloadPaths()` is required when view classes inherit through custom intermediate classes:

```php
// rector.php
use Joomla\Rector\Joomla3\ViewAssignRefToPropertyRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(ViewAssignRefToPropertyRector::class);

    $rectorConfig->autoloadPaths([
        __DIR__ . '/joomla',
    ]);
};
```

---

## ApplicationInputPropertyRector

**Class:** `Joomla\Rector\Joomla5\ApplicationInputPropertyRector`

Replaces `$var->input` with `$var->getInput()` inside method and function bodies where `$var` was assigned from any of the following calls:

- `Factory::getApplication()`
- `JFactory::getApplication()`
- `\Joomla\CMS\Factory::getApplication()`
- `$this->getApplication()`

Variable tracking is scoped per method or function body, so the variable name (`$app`, `$application`, etc.) can be anything.

In Joomla 4 and earlier the `$input` property was publicly accessible on application objects. Joomla 5 formalises access through the `getInput()` method. Direct property access still works due to backward compatibility, but using the method is the current best practice and required for forward compatibility.

### Before / After

`Factory::getApplication()` and `JFactory::getApplication()`:

```php
// Before
class MyController extends BaseController
{
    public function execute(string $task): void
    {
        $app   = Factory::getApplication();
        $name  = $app->input->get('name', '', 'string');
        $input = $app->input;
    }

    public function save(): void
    {
        $app  = JFactory::getApplication();
        $data = $app->input->getArray();
    }
}
```

```php
// After
class MyController extends BaseController
{
    public function execute(string $task): void
    {
        $app   = Factory::getApplication();
        $name  = $app->getInput()->get('name', '', 'string');
        $input = $app->getInput();
    }

    public function save(): void
    {
        $app  = JFactory::getApplication();
        $data = $app->getInput()->getArray();
    }
}
```

`$this->getApplication()` (common in controllers, modules, and plugins):

```php
// Before
class MyPlugin extends CMSPlugin
{
    public function onContentPrepare(): void
    {
        $app   = $this->getApplication();
        $name  = $app->input->get('name', '', 'string');
        $input = $app->input;
    }
}
```

```php
// After
class MyPlugin extends CMSPlugin
{
    public function onContentPrepare(): void
    {
        $app   = $this->getApplication();
        $name  = $app->getInput()->get('name', '', 'string');
        $input = $app->getInput();
    }
}
```

Chained access is handled correctly — `$app->input->get(...)` becomes `$app->getInput()->get(...)`.

### What is NOT changed

- `->input` on variables that are not directly assigned from a recognised `getApplication()` call in the same method or function body.

### Configuration

The rule requires no configuration parameters.

```php
// rector.php
use Joomla\Rector\Joomla5\ApplicationInputPropertyRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(ApplicationInputPropertyRector::class);
};
```

---

## CurrentUserInterfaceGetUserRector

**Class:** `Joomla\Rector\Joomla5\CurrentUserInterfaceGetUserRector`

Replaces `Factory::getUser()` and `JFactory::getUser()` calls with `$this->getCurrentUser()` in classes that implement `\Joomla\CMS\User\CurrentUserInterface` — either directly in the `implements` list or through inheritance from a Joomla core class such as `BaseDatabaseModel` or `BaseController`.

The rule checks for direct implementation first (AST-only, no reflection). For inherited implementations it falls back to PHPStan's `ReflectionProvider`, which requires the Joomla class hierarchy to be available via `autoloadPaths()`.

### Before / After

```php
// Before — direct implementation
class ExampleController implements \Joomla\CMS\User\CurrentUserInterface
{
    public function isAllowed(): bool
    {
        $user = Factory::getUser();
        return $user->authorise('core.edit', 'com_example');
    }
}
```

```php
// After
class ExampleController implements \Joomla\CMS\User\CurrentUserInterface
{
    public function isAllowed(): bool
    {
        $user = $this->getCurrentUser();
        return $user->authorise('core.edit', 'com_example');
    }
}
```

Both `Factory::getUser()` and `JFactory::getUser()` (including the FQN `\Joomla\CMS\Factory::getUser()`) are replaced. Calls with arguments are left untouched.

Inherited implementation is also detected when the Joomla sources are available:

```php
// Before — inherits CurrentUserInterface from BaseDatabaseModel
class ExampleModel extends \Joomla\CMS\MVC\Model\BaseDatabaseModel
{
    public function isAllowed(): bool
    {
        $user1 = Factory::getUser();
        $user2 = JFactory::getUser();
        return $user1->authorise('core.edit', 'com_example');
    }
}
```

```php
// After
class ExampleModel extends \Joomla\CMS\MVC\Model\BaseDatabaseModel
{
    public function isAllowed(): bool
    {
        $user1 = $this->getCurrentUser();
        $user2 = $this->getCurrentUser();
        return $user1->authorise('core.edit', 'com_example');
    }
}
```

### Configuration

The rule requires no configuration parameters. To enable detection through inherited implementations, point `autoloadPaths()` to the Joomla source or to the generated stubs:

```php
// rector.php
use Joomla\Rector\Joomla5\CurrentUserInterfaceGetUserRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(CurrentUserInterfaceGetUserRector::class);

    // Required for detection through inheritance
    $rectorConfig->autoloadPaths([
        __DIR__ . '/stubs/src',
        __DIR__ . '/stubs/vendor/joomla',
    ]);
};
```

---

## GetDboToGetDatabaseRector

**Class:** `Joomla\Rector\Joomla5\GetDboToGetDatabaseRector`

Replaces deprecated `getDbo()` calls with `getDatabase()` in classes that use `\Joomla\Database\DatabaseAwareTrait` — either directly or through a parent class such as `BaseDatabaseModel`. All three call forms are rewritten:

| Before | After |
|---|---|
| `$this->getDbo()` | `$this->getDatabase()` |
| `Factory::getDbo()` | `$this->getDatabase()` |
| `JFactory::getDbo()` | `$this->getDatabase()` |

The rule uses PHPStan's `ReflectionProvider` with `getTraits(true)` to detect trait usage across the full inheritance chain, so the Joomla class hierarchy must be available via `autoloadPaths()`.

### Before / After

```php
// Before
class ExampleModel extends \Joomla\CMS\MVC\Model\BaseDatabaseModel
{
    public function getItems(): array
    {
        $db1 = $this->getDbo();
        $db2 = Factory::getDbo();

        return $db1->loadObjectList();
    }
}
```

```php
// After
class ExampleModel extends \Joomla\CMS\MVC\Model\BaseDatabaseModel
{
    public function getItems(): array
    {
        $db1 = $this->getDatabase();
        $db2 = $this->getDatabase();

        return $db1->loadObjectList();
    }
}
```

### Configuration

The rule requires no configuration parameters. `autoloadPaths()` is required to detect trait usage through parent classes:

```php
// rector.php
use Joomla\Rector\Joomla5\GetDboToGetDatabaseRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(GetDboToGetDatabaseRector::class);

    $rectorConfig->autoloadPaths([
        __DIR__ . '/stubs/src',
        __DIR__ . '/stubs/vendor/joomla',
    ]);
};
```

---

## JimportRector

**Class:** `Joomla\Rector\Joomla4\JimportRector`

Removes `jimport('joomla.*')` calls that are redundant in Joomla 4 and later. The Joomla 4 autoloader loads all core classes automatically, so any `jimport()` call whose argument starts with `joomla.` can be safely deleted.

Only standalone expression statements are removed. `jimport()` calls embedded in assignments or conditions are left untouched.

### Before / After

```php
// Before
jimport('joomla.application.component.view');
jimport('joomla.utilities.string');
jimport('joomla.environment.request');

class SomeView {}
```

```php
// After
class SomeView {}
```

### Configuration

The rule requires no configuration parameters.

```php
// rector.php
use Joomla\Rector\Joomla4\JimportRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(JimportRector::class);
};
```

---

## HtmlViewGetToModelGetRector

**Class:** `Joomla\Rector\Joomla5\HtmlViewGetToModelGetRector`

Replaces `$this->get('Foo')` calls inside Joomla `HtmlView` classes with the equivalent direct model getter `$model->getFoo()`. If the method does not already have a `$model` variable, the rule prepends `$model = $this->getModel()` once at the top of the method.

When the class follows the Joomla 4 MVC namespace convention (`...\View\<Name>\HtmlView`), the rule additionally adds a `/** @var \...\Model\<Name>Model $model */` typehint comment above the `$model = $this->getModel()` line. The model FQN is derived automatically by replacing `\View\` with `\Model\`, removing the `\HtmlView` class name, and appending `Model`.

The rule only applies to classes whose short name is exactly `HtmlView`. Other classes that happen to call `$this->get()` are left untouched.

### Before / After

With namespace (comment is generated automatically):

```php
// Before
namespace Acme\Component\Example\Site\View\Articles;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $items      = $this->get('Items');
        $pagination = $this->get('Pagination');
    }
}
```

```php
// After
namespace Acme\Component\Example\Site\View\Articles;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        /** @var \Acme\Component\Example\Site\Model\ArticlesModel $model */
        $model      = $this->getModel();
        $items      = $model->getItems();
        $pagination = $model->getPagination();
    }
}
```

When `$model = $this->getModel()` is already present in the method, only the comment is added and the `$this->get()` calls are replaced — no duplicate assignment:

```php
// Before
namespace Acme\Component\Example\Site\View\Articles;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $items = $this->get('Items');
    }
}
```

```php
// After
namespace Acme\Component\Example\Site\View\Articles;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        /** @var \Acme\Component\Example\Site\Model\ArticlesModel $model */
        $model = $this->getModel();
        $items = $model->getItems();
    }
}
```

Without namespace the `@var` comment is omitted (model FQN cannot be derived).

### Configuration

The rule requires no configuration parameters.

```php
// rector.php
use Joomla\Rector\Joomla5\HtmlViewGetToModelGetRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(HtmlViewGetToModelGetRector::class);
};
```

---

## ViewThisTypehintRector

**Class:** `Joomla\Rector\Joomla5\ViewThisTypehintRector`

Adds a `/** @var FullyQualifiedViewClass $this */` doc comment to Joomla view template files located inside `tmpl/<viewname>/` directories. This gives IDEs and static analysis tools accurate type information for `$this` inside layout files, which are included at runtime from an `HtmlView` context.

### How it works

1. The rule scans every PHP file whose path matches `tmpl/<viewname>/<template>.php`.
2. It locates the corresponding view class at `src/View/<viewname>/HtmlView.php` relative to the component root (the directory that contains the `tmpl/` folder).
3. It reads the `namespace` and `class` declarations from that file to build the fully-qualified class name.
4. It prepends the `@var` annotation to the first PHP statement in the template, unless the annotation is already present.

### Before / After

Given the following component structure:

```
src/
  View/
    Articles/
      HtmlView.php   ← namespace Acme\Component\Example\Site\View\Articles; class HtmlView
tmpl/
  articles/
    default.php
```

```php
// Before: tmpl/articles/default.php
<?php
defined('_JEXEC') or die;
$items = $this->items;
```

```php
// After: tmpl/articles/default.php
<?php
/** @var \Acme\Component\Example\Site\View\Articles\HtmlView $this */
defined('_JEXEC') or die;
$items = $this->items;
```

### Configuration

The rule requires no configuration parameters.

```php
// rector.php
use Joomla\Rector\Joomla5\ViewThisTypehintRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(ViewThisTypehintRector::class);
};
```

---

## HtmlViewExceptionHandlingRector

**Class:** `Joomla\Rector\Joomla6\HtmlViewExceptionHandlingRector`

Modernises error handling in Joomla `HtmlView` classes for Joomla 6, which introduces exception-based error propagation from models. The rule performs two transformations in every method of an `HtmlView` class:

1. **Adds `$model->setUseException(true)`** immediately after every `$model = $this->getModel()` assignment that is not already followed by that call.
2. **Removes legacy `if (count($errors = $model->getErrors())) { ... }` blocks** — including any leading comments — since exceptions now propagate automatically when `setUseException(true)` is active.

A class qualifies as an `HtmlView` if it extends `\Joomla\CMS\MVC\View\AbstractView` directly or via any parent class. Detection uses PHPStan's `ReflectionProvider` and therefore requires `autoloadPaths()`.

### Before / After

Full transformation — `setUseException` inserted, `getErrors()` block removed:

```php
// Before
class ExampleHtmlView extends \Joomla\CMS\MVC\View\HtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();

        // Check for errors.
        if (count($errors = $model->getErrors())) {
            throw new \Exception(implode("\n", $errors));
        }

        $items = $model->getItems();
    }
}
```

```php
// After
class ExampleHtmlView extends \Joomla\CMS\MVC\View\HtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $model->setUseException(true);

        $items = $model->getItems();
    }
}
```

When `setUseException(true)` is already present, only the `getErrors()` block is removed:

```php
// Before — setUseException already present
class ExampleHtmlView extends \Joomla\CMS\MVC\View\HtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $model->setUseException(true);

        if (count($errors = $model->getErrors())) {
            throw new \Exception(implode("\n", $errors));
        }

        $items = $model->getItems();
    }
}
```

```php
// After
class ExampleHtmlView extends \Joomla\CMS\MVC\View\HtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $model->setUseException(true);

        $items = $model->getItems();
    }
}
```

### Configuration

The rule requires no configuration parameters. `autoloadPaths()` is required to detect the `AbstractView` ancestry through the Joomla class hierarchy:

```php
// rector.php
use Joomla\Rector\Joomla6\HtmlViewExceptionHandlingRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(HtmlViewExceptionHandlingRector::class);

    $rectorConfig->autoloadPaths([
        __DIR__ . '/stubs/src',
        __DIR__ . '/stubs/vendor/joomla',
    ]);
};
```
