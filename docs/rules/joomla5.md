# Joomla 5 rules

Rules that bring code up to Joomla 5.

Back to the [rule index](../rules.md).

- [ApplicationInputPropertyRector](#applicationinputpropertyrector)
- [CurrentUserInterfaceGetUserRector](#currentuserinterfacegetuserrector)
- [GetDboToGetDatabaseRector](#getdbotogetdatabaserector)
- [HtmlViewGetToModelGetRector](#htmlviewgettomodelgetrector)
- [LegacyPropertyManagementGetSetRector](#legacypropertymanagementgetsetrector)
- [PluginPropertyToGetterRector](#pluginpropertytogetterrector)
- [PluginSubscriberInterfaceRector](#pluginsubscriberinterfacerector)
- [TableGetInstanceRector](#tablegetinstancerector)
- [ToolbarHelperToDocumentToolbarRector](#toolbarhelpertodocumenttoolbarrector)
- [ViewThisTypehintRector](#viewthistypehintrector)

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

## LegacyPropertyManagementGetSetRector

**Class:** `Joomla\Rector\Joomla5\LegacyPropertyManagementGetSetRector`

Replaces `$this->get('key', $default)` and `$this->set('key', $value)` calls with direct property access in classes that use `\Joomla\CMS\Object\LegacyPropertyManagementTrait`.

The `LegacyPropertyManagementTrait` provided `get()` / `set()` accessors as a compatibility layer for the old `CMSObject` property API. In modern Joomla code the properties are accessed directly, which is clearer and more performant.

| Before | After |
|---|---|
| `$this->get('key', $default)` | `$this->key ?? $default` |
| `$this->get('key')` | `$this->key ?? null` |
| `$this->set('key', $value)` | `$this->key = $value` |

The rule applies to any class that uses `LegacyPropertyManagementTrait` directly or inherits it from a parent class. Direct trait usage is detected from the AST; inherited usage is resolved via PHPStan's `ReflectionProvider`, which requires `autoloadPaths()`.

### Before / After

Class with direct trait use:

```php
// Before
use Joomla\CMS\Object\LegacyPropertyManagementTrait;

class ExampleView
{
    use LegacyPropertyManagementTrait;

    public function display(): void
    {
        $title  = $this->get('title', '');
        $limit  = $this->get('limit', $this->getDefaultLimit());
        $active = $this->get('active');

        $this->set('active', true);
        $this->set('title', 'New Title');
    }
}
```

```php
// After
use Joomla\CMS\Object\LegacyPropertyManagementTrait;

class ExampleView
{
    use LegacyPropertyManagementTrait;

    public function display(): void
    {
        $title  = $this->title ?? '';
        $limit  = $this->limit ?? $this->getDefaultLimit();
        $active = $this->active ?? null;

        $this->active = true;
        $this->title = 'New Title';
    }
}
```

The default value for `->get()` is passed through unchanged — variables, method calls, and array literals are all preserved.

### What is NOT changed

- Calls where the first argument is not a string literal (dynamic keys cannot be safely converted to a property access).
- `$this->set('key')` calls with only one argument are left untouched (no value to assign).
- Classes that do not use `LegacyPropertyManagementTrait` (directly or via inheritance) are skipped entirely.
- Only `$this->get()` / `$this->set()` are handled. Calls on other objects (`$model->get(...)`) require type inference and are out of scope.

### Configuration

The rule requires no configuration parameters. `autoloadPaths()` is required when the trait is inherited through parent classes:

```php
// rector.php
use Joomla\Rector\Joomla5\LegacyPropertyManagementGetSetRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(LegacyPropertyManagementGetSetRector::class);

    // Required for detection through inheritance
    $rectorConfig->autoloadPaths([
        __DIR__ . '/stubs/src',
        __DIR__ . '/stubs/vendor/joomla',
    ]);
};
```

---

## PluginPropertyToGetterRector

**Class:** `Joomla\Rector\Joomla5\PluginPropertyToGetterRector`

In classes that directly or indirectly extend `Joomla\CMS\Plugin\CMSPlugin`:

- Replaces every `$this->app` access with `$this->getApplication()` when the class has a `$app` property.
- Replaces every `$this->db` access with `$this->getDatabase()` when the class has a `$db` property.

Property detection first checks the class's own declarations (AST). For direct `CMSPlugin` subclasses the check short-circuits to `true` immediately because `CMSPlugin` always declares both `$app` and `$db`. For indirect subclasses PHPStan's `ReflectionProvider` walks the full parent chain, which requires `autoloadPaths()`.

### Before / After

```php
// Before
use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentExample extends CMSPlugin
{
    public function onContentPrepare(): void
    {
        $app   = $this->app;
        $this->app->enqueueMessage('test');

        $query = $this->db->getQuery(true);
        $this->db->setQuery($query);
    }
}
```

```php
// After
use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentExample extends CMSPlugin
{
    public function onContentPrepare(): void
    {
        $app   = $this->getApplication();
        $this->getApplication()->enqueueMessage('test');

        $query = $this->getDatabase()->getQuery(true);
        $this->getDatabase()->setQuery($query);
    }
}
```

Both `$this->app` and `$this->db` in chained calls are handled — `$this->app->enqueueMessage(...)` becomes `$this->getApplication()->enqueueMessage(...)`.

### What is NOT changed

- Classes that do not extend `CMSPlugin` (directly or indirectly) are skipped entirely.
- If the class has neither a `$app` nor a `$db` property (own or inherited), nothing is changed.

### Configuration

The rule requires no configuration parameters. `autoloadPaths()` is required when the plugin inherits through a custom intermediate class:

```php
// rector.php
use Joomla\Rector\Joomla5\PluginPropertyToGetterRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(PluginPropertyToGetterRector::class);

    $rectorConfig->autoloadPaths([
        __DIR__ . '/joomla',
    ]);
};
```

---

## PluginSubscriberInterfaceRector

**Class:** `Joomla\Rector\Joomla5\PluginSubscriberInterfaceRector`

Adds `Joomla\Event\SubscriberInterface` to the `implements` list of plugin classes and inserts a generated `getSubscribedEvents()` method when both of the following are true:

1. The class directly or indirectly extends `Joomla\CMS\Plugin\CMSPlugin`.
2. The class does not yet implement `Joomla\Event\SubscriberInterface` (neither directly nor through inheritance).

Joomla 4 used magic event method names (`onContentPrepare`, `onUserLogin`, …) discovered via reflection. Joomla 5 requires plugins to explicitly declare which events they subscribe to via `SubscriberInterface::getSubscribedEvents()`.

The generated `getSubscribedEvents()` method returns one entry per public, non-static, non-magic method defined in the class body, using the method name as both the event name (key) and the handler name (value). Magic methods (`__construct`, `__destruct`, …) and static methods are excluded.

Direct extension of `CMSPlugin` is detected via the AST (no reflection needed). For classes that extend a custom intermediate plugin class, PHPStan's `ReflectionProvider` walks the full parent chain and therefore requires `autoloadPaths()`.

### Before / After

```php
// Before
use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentExample extends CMSPlugin
{
    public function __construct(&$subject, array $config = [])
    {
        parent::__construct($subject, $config);
    }

    public function onContentPrepare(): void {}
    public function onUserLogin(): bool { return true; }
}
```

```php
// After
use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentExample extends CMSPlugin implements \Joomla\Event\SubscriberInterface
{
    public function __construct(&$subject, array $config = [])
    {
        parent::__construct($subject, $config);
    }

    public function onContentPrepare(): void {}
    public function onUserLogin(): bool { return true; }

    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare', 'onUserLogin' => 'onUserLogin'];
    }
}
```

### What is NOT changed

- Classes that already implement `Joomla\Event\SubscriberInterface` (directly or via inheritance) are skipped.
- Classes that do not extend `CMSPlugin` (directly or indirectly) are skipped.
- PHP magic methods (`__construct`, `__destruct`, …) and static methods are not included in `getSubscribedEvents()`.

### Manual follow-up

The generated `getSubscribedEvents()` uses each method name as both the event key and the handler. Review the generated entries:
- Rename keys to the actual Joomla event names if the method names differ (e.g. `onContentAfterSave` → `ContentAfterSave` in some Joomla 5 event dispatcher configurations).
- Remove entries for helper methods that are public but not event handlers.

### Configuration

The rule requires no configuration parameters. `autoloadPaths()` is required when plugin classes inherit through custom intermediate classes:

```php
// rector.php
use Joomla\Rector\Joomla5\PluginSubscriberInterfaceRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(PluginSubscriberInterfaceRector::class);

    $rectorConfig->autoloadPaths([
        __DIR__ . '/joomla',
    ]);
};
```

---

## TableGetInstanceRector

**Class:** `Joomla\Rector\Joomla5\TableGetInstanceRector`

Replaces `Table::getInstance($type)` static calls with direct class instantiation. In Joomla 5, `Table::getInstance()` is deprecated in favour of injecting a `$db` instance and calling `new TableClass($db)` directly.

The replacement class FQN is resolved as follows:

1. If `component_namespace` is configured and the component-specific table class `<component_namespace>\Administrator\Table\<Type>Table` can be found (via PHPStan's `ReflectionProvider` — requires `autoloadPaths()`), that class is used.
2. Otherwise the rule falls back to the core namespace `\Joomla\CMS\Table\<Type>`.

Only assignment expressions of the form `$var = Table::getInstance('Type')` are handled. Non-assignment contexts (return statements, conditions, chained calls) are left untouched. If the optional second argument (the legacy class prefix) is present, it must be `'JTable'` — any other prefix cannot be reliably resolved and is therefore skipped.

### Before / After

Core Joomla table (no component namespace configured or class not found):

```php
// Before
use Joomla\CMS\Table\Table;

class MyModel
{
    public function getContentTable(): void
    {
        $table = Table::getInstance('Content');
    }
}
```

```php
// After
use Joomla\CMS\Table\Table;

class MyModel
{
    public function getContentTable(): void
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $table = new \Joomla\CMS\Table\Content($db);
    }
}
```

Component-specific table (when `component_namespace` is set and the class exists):

```php
// Before
$table = \Joomla\CMS\Table\Table::getInstance('Article');
```

```php
// After (with component_namespace = 'Acme\Component\Example' and ArticleTable exists)
$db = \Joomla\CMS\Factory::getDbo();
$table = new \Acme\Component\Example\Administrator\Table\ArticleTable($db);
```

The `JTable` default prefix is also accepted:

```php
// Before
$table = Table::getInstance('User', 'JTable');
```

```php
// After
$db = \Joomla\CMS\Factory::getDbo();
$table = new \Joomla\CMS\Table\User($db);
```

### What is NOT changed

- Calls where the type argument is not a string literal (dynamic types cannot be resolved).
- Calls with a non-`JTable` second argument (custom prefix — class name cannot be reliably derived).
- Non-assignment contexts (`return Table::getInstance(...)`, conditions, method-chain bases).

### Configuration

The rule accepts an optional `component_namespace` parameter. Without it the rule always uses the core `\Joomla\CMS\Table\<Type>` fallback.

```php
// rector.php
use Joomla\Rector\Joomla5\TableGetInstanceRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    // Without component namespace — always uses \Joomla\CMS\Table\<Type>
    $rectorConfig->rule(TableGetInstanceRector::class);
};
```

With a component namespace (requires `autoloadPaths()` so that `ReflectionProvider` can resolve the table class):

```php
// rector.php
use Joomla\Rector\Joomla5\TableGetInstanceRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->ruleWithConfiguration(TableGetInstanceRector::class, [
        TableGetInstanceRector::COMPONENT_NAMESPACE => 'Acme\\Component\\Example',
    ]);

    $rectorConfig->autoloadPaths([
        __DIR__ . '/src',
    ]);
};
```

---

## ToolbarHelperToDocumentToolbarRector

**Class:** `Joomla\Rector\Joomla5\ToolbarHelperToDocumentToolbarRector`

Replaces static `ToolbarHelper::<method>()` calls with instance calls on a `$toolbar` variable obtained from `$this->getDocument()->getToolbar()`. This is required in Joomla 5, where the `ToolbarHelper` static API is deprecated in favour of the object-oriented `Toolbar` instance exposed through `DocumentAwareInterface`.

The rule applies to any class that implements `\Joomla\CMS\Document\DocumentAwareInterface`, either directly or through a parent class. Direct implementation is detected via the AST; indirect implementation is resolved via PHPStan's `ReflectionProvider`, which requires `autoloadPaths()`.

For each qualifying method:

1. `$toolbar = $this->getDocument()->getToolbar()` is inserted once, immediately before the first `ToolbarHelper` call in the method body.
2. All `ToolbarHelper::<method>(args...)` calls are replaced with `$toolbar-><method>(args...)`.
3. If `$toolbar` is already assigned in the method, step 1 is skipped (no duplicate assignment).

`ToolbarHelper::title()` is excluded from the replacement — it does not have a direct instance equivalent.

Both the short name (`ToolbarHelper`) and the fully-qualified name (`\Joomla\CMS\Toolbar\ToolbarHelper`) are recognised.

### Before / After

Basic replacement:

```php
// Before
use Joomla\CMS\Document\DocumentAwareInterface;

class ExampleView implements DocumentAwareInterface
{
    public function addToolbar(): void
    {
        ToolbarHelper::addNew('article.add');
        ToolbarHelper::editList('article.edit');
        ToolbarHelper::deleteList('', 'article.delete');
    }
}
```

```php
// After
use Joomla\CMS\Document\DocumentAwareInterface;

class ExampleView implements DocumentAwareInterface
{
    public function addToolbar(): void
    {
        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->addNew('article.add');
        $toolbar->editList('article.edit');
        $toolbar->deleteList('', 'article.delete');
    }
}
```

`ToolbarHelper::title()` is left in place; the `$toolbar` assignment is inserted only before the first non-title call:

```php
// Before
public function addToolbar(): void
{
    ToolbarHelper::title('Articles', 'article');
    ToolbarHelper::addNew('article.add');
}
```

```php
// After
public function addToolbar(): void
{
    ToolbarHelper::title('Articles', 'article');
    $toolbar = $this->getDocument()->getToolbar();
    $toolbar->addNew('article.add');
}
```

When `$toolbar` is already assigned in the method, only the calls are replaced:

```php
// Before
public function addToolbar(): void
{
    $toolbar = $this->getDocument()->getToolbar();
    ToolbarHelper::addNew('article.add');
}
```

```php
// After
public function addToolbar(): void
{
    $toolbar = $this->getDocument()->getToolbar();
    $toolbar->addNew('article.add');
}
```

### What is NOT changed

- `ToolbarHelper::title()` — excluded from the replacement.
- Classes that do not implement `DocumentAwareInterface` (directly or indirectly) are skipped entirely.

### Configuration

The rule requires no configuration parameters. `autoloadPaths()` is required to detect `DocumentAwareInterface` implementation through parent classes:

```php
// rector.php
use Joomla\Rector\Joomla5\ToolbarHelperToDocumentToolbarRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(ToolbarHelperToDocumentToolbarRector::class);

    // Required for detection through inheritance
    $rectorConfig->autoloadPaths([
        __DIR__ . '/stubs/src',
        __DIR__ . '/stubs/vendor/joomla',
    ]);
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
4. It inserts the `@var` annotation into the first PHP statement's leading comments, unless the annotation is already present. If that statement already carries a file-header docblock (one containing `@package`, `@copyright`, or `@license`), the annotation is placed after the header; otherwise it is placed before all other comments.

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

Template without a file-header docblock — annotation is prepended:

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

Template with a file-header docblock — annotation is inserted after it:

```php
// Before: tmpl/articles/default.php
<?php
/**
 * @package     Acme.Example
 * @subpackage  Site
 *
 * @copyright   (C) 2024 Acme, Inc.
 * @license     GNU General Public License version 2 or later
 */
defined('_JEXEC') or die;
$items = $this->items;
```

```php
// After: tmpl/articles/default.php
<?php
/**
 * @package     Acme.Example
 * @subpackage  Site
 *
 * @copyright   (C) 2024 Acme, Inc.
 * @license     GNU General Public License version 2 or later
 */
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
