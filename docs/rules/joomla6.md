# Joomla 6 rules

Rules that bring code up to Joomla 6.

Back to the [rule index](../rules.md).

- [CmsObjectReturnTypeRector](#cmsobjectreturntyperector)
- [EventArgumentsToTypedEventRector](#eventargumentstotypedeventrector)
- [HtmlViewExceptionHandlingRector](#htmlviewexceptionhandlingrector)
- [SetErrorToExceptionRector](#seterrortoexceptionrector)

---

## CmsObjectReturnTypeRector

**Class:** `Joomla\Rector\Joomla6\CmsObjectReturnTypeRector`

Replaces `CMSObject` with `stdClass` in return type declarations, `@return` PHPDoc tags, property type declarations, and `@var` PHPDoc tags. The `Joomla\CMS\Object\CMSObject` class was removed in Joomla 6; all occurrences in type positions must be updated to the plain `stdClass` equivalent.

Both the short name (`CMSObject`) and the fully-qualified name (`\Joomla\CMS\Object\CMSObject` / `Joomla\CMS\Object\CMSObject`) are recognised. All native type forms are handled: simple, nullable (`?CMSObject`), union (`CMSObject|false`), and intersection types.

### Before / After

Simple return typehint and matching `@return` tag:

```php
// Before
class ExampleModel
{
    /**
     * @return CMSObject
     */
    public function getItem(): CMSObject
    {
        return new CMSObject();
    }
}
```

```php
// After
class ExampleModel
{
    /**
     * @return stdClass
     */
    public function getItem(): stdClass
    {
        return new CMSObject();
    }
}
```

Nullable and union typehints:

```php
// Before
public function findItem(): ?CMSObject { ... }
public function getResult(): CMSObject|false { ... }
```

```php
// After
public function findItem(): ?stdClass { ... }
public function getResult(): stdClass|false { ... }
```

Fully-qualified names in both typehints and PHPDoc:

```php
// Before
/**
 * @return \Joomla\CMS\Object\CMSObject
 */
public function getItem(): \Joomla\CMS\Object\CMSObject { ... }
```

```php
// After
/**
 * @return stdClass
 */
public function getItem(): stdClass { ... }
```

Property type hints and `@var` tags:

```php
// Before
class ExampleModel
{
    /**
     * @var CMSObject
     */
    public CMSObject $item;

    public ?CMSObject $related = null;
}
```

```php
// After
class ExampleModel
{
    /**
     * @var stdClass
     */
    public stdClass $item;

    public ?stdClass $related = null;
}
```

Standalone functions are also covered:

```php
// Before
/** @return CMSObject */
function getGlobalItem(): CMSObject { ... }
```

```php
// After
/** @return stdClass */
function getGlobalItem(): stdClass { ... }
```

### What is NOT changed

- `@param` PHPDoc tags — only `@return` and `@var` lines are touched.
- Parameter type hints — only return types and property types are replaced.
- `new CMSObject()` instantiation expressions — those are out of scope for this rule.
- Classes that extend or implement `CMSObject` — inheritance hierarchy changes are a separate concern.

### Configuration

The rule requires no configuration parameters.

```php
// rector.php
use Joomla\Rector\Joomla6\CmsObjectReturnTypeRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(CmsObjectReturnTypeRector::class);
};
```

---

## EventArgumentsToTypedEventRector

**Class:** `Joomla\Rector\Joomla6\Plugin\EventArgumentsToTypedEventRector`

Replaces positional and named access to event arguments with the typed getters of the concrete event class. Joomla 4 and 5 guaranteed through the `$legacyArgumentsOrder` property of the concrete event classes that the order of the event arguments matches the parameter order of the old plugin methods. Joomla 6 drops that guarantee, so any code that reads the arguments by position breaks silently rather than loudly.

The rule only acts inside methods that receive a **typed** event parameter, e.g. `ContentPrepareEvent $event`. If the parameter is untyped or typed against a generic `Event` / `GenericEvent`, the concrete event class cannot be determined and the method is left untouched — typing the signature first is the job of the legacy handler signature rule.

The argument name cannot be translated into a getter name by convention. `ContentPrepareEvent` stores its argument under the key `subject` but exposes it as `getItem()`. The rule therefore resolves getters from a map that is **generated from the Joomla core source** by `tools/generate-event-argument-map.php`, which reads both `$legacyArgumentsOrder` and the bodies of the public `get*()` methods of every event class in `libraries/src/Event/**`. Re-run the generator against a newer Joomla checkout to refresh the map:

```
php tools/generate-event-argument-map.php /path/to/joomla
```

PHPStan reflection is used only to map a project's own subclass of a known event class onto its mapped ancestor; the default map itself never depends on reflection.

### Before / After

Positional destructuring:

```php
// Before
use Joomla\CMS\Event\Content\ContentPrepareEvent;

class PlgContentExample extends CMSPlugin implements SubscriberInterface
{
    public function myOnContentPrepare(ContentPrepareEvent $event)
    {
        [$context, $item, $params, $page] = array_values($event->getArguments());

        if ($context !== 'com_content.article') {
            return;
        }

        $item->text .= 'foo';
    }
}
```

```php
// After
use Joomla\CMS\Event\Content\ContentPrepareEvent;

class PlgContentExample extends CMSPlugin implements SubscriberInterface
{
    public function myOnContentPrepare(ContentPrepareEvent $event)
    {
        $context = $event->getContext();
        $item = $event->getItem();
        $params = $event->getParams();
        $page = $event->getPage();

        if ($context !== 'com_content.article') {
            return;
        }

        $item->text .= 'foo';
    }
}
```

Named access, both through `getArgument()` and through `ArrayAccess`:

```php
// Before
$context = $event->getArgument('context');
$item    = $event['subject'];
```

```php
// After
$context = $event->getContext();
$item    = $event->getItem();
```

### What is NOT changed

- Handlers whose event parameter is untyped, nullable, a union or intersection type, or typed against `Joomla\Event\Event`, `Joomla\CMS\Event\AbstractEvent`, `Joomla\CMS\Event\AbstractImmutableEvent` or `GenericEvent`.
- Event classes that are neither in the generated default map nor in the configured map, and that do not inherit from a class that is.
- Destructuring with more targets than the event has known arguments. Destructuring a **prefix** of the argument list is converted, since that is equivalent.
- Destructuring with holes (`[$context, , $params]`), keyed targets (`['context' => $c]`), by-reference targets (`[$context, &$item]`), or nested destructuring.
- Indexed access through an intermediate variable (`$args = $event->getArguments(); $args[0]`).
- Numeric argument access — `$event->getArgument(0)`, `$event->getArgument('1')` and `$event[2]`. Joomla deprecated it and it does not identify an argument reliably.
- Dynamic argument names (`$event->getArgument($name)`, `$event[$name]`).
- `$event->getArgument('name', $default)` with a default value, because the typed getters have no equivalent for it.
- Write, unset and existence access: `$event['params'] = ...`, `unset($event['page'])`, `isset($event['page'])`, `empty($event['context'])`.
- The `result` argument, which belongs to the event result handling.
- Methods in which the event variable is reassigned, since later accesses may no longer refer to the event.

### Manual follow-up

- Events with a result value additionally need the return value of the handler converted — that is a separate concern and not done by this rule.
- Reference semantics: replacing an argument read with a getter is equivalent for objects, because they are handles. If legacy code relied on a `&$item` **scalar** parameter and wrote back to it, the rewrite is not equivalent. The patterns this rule converts all copy values already, so it does not introduce the problem, but code migrated from a by-reference legacy signature should be reviewed.
- For custom event classes the getters must be registered through the configuration below, otherwise those handlers are skipped.
- The rule leaves the `use` statement of the event class in place; it is still needed for the parameter type.

### Configuration

The rule works without configuration — the default map covers the Joomla core events. `autoloadPaths()` is **not** required for those. It is only needed if you want a project's own event subclasses to be resolved through their mapped parent class.

Custom event classes can be registered in three notations:

```php
// rector.php
use Joomla\Rector\Joomla6\Plugin\EventArgumentsToTypedEventRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->ruleWithConfiguration(EventArgumentsToTypedEventRector::class, [
        EventArgumentsToTypedEventRector::EVENT_ARGUMENT_MAP => [
            // 1. Positional list — getters are derived as get<Name>()
            \Acme\Event\SimpleEvent::class => ['context', 'item', 'params', 'page'],

            // 2. Argument name => getter, when the getter name differs from the argument name
            \Acme\Event\RenamedEvent::class => ['context' => 'getContext', 'subject' => 'getItem'],

            // 3. Both parts explicitly, when the positional order and the getters differ
            \Acme\Event\FullEvent::class => [
                'order'   => ['context', 'subject'],
                'getters' => ['context' => 'getContext', 'subject' => 'getItem'],
            ],
        ],
    ]);
};
```

Configured entries override entries of the same class name in the default map.

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

---

## SetErrorToExceptionRector

**Class:** `Joomla\Rector\Joomla6\SetErrorToExceptionRector`

Replaces legacy `$this->setError()` / `return false` error-handling pairs with a thrown `\Exception`. In Joomla 3 and 4, models and controllers often signalled failure by storing an error message via `$this->setError('...')` and returning `false`. Joomla 6 promotes exception-based error propagation.

The rule matches consecutive statement pairs of the form:

```
$this->setError(<expr>);
return false;
```

and replaces them with:

```
throw new \Exception(<expr>);
```

The transformation recurses into nested blocks: `if`, `else`, `elseif`, `foreach`, `for`, `while`, and `try/catch` bodies are all processed.

### Before / After

Simple method body:

```php
// Before
class ExampleModel
{
    public function save(array $data): bool
    {
        if (!$this->validate($data)) {
            $this->setError('Validation failed');
            return false;
        }

        return true;
    }
}
```

```php
// After
class ExampleModel
{
    public function save(array $data): bool
    {
        if (!$this->validate($data)) {
            throw new \Exception('Validation failed');
        }

        return true;
    }
}
```

Nested blocks and multiple occurrences:

```php
// Before
public function process(): bool
{
    foreach ($this->items as $item) {
        if (!$item->isValid()) {
            $this->setError('Invalid item');
            return false;
        }
    }

    if (!$this->store()) {
        $this->setError('Store failed');
        return false;
    }

    return true;
}
```

```php
// After
public function process(): bool
{
    foreach ($this->items as $item) {
        if (!$item->isValid()) {
            throw new \Exception('Invalid item');
        }
    }

    if (!$this->store()) {
        throw new \Exception('Store failed');
    }

    return true;
}
```

### What is NOT changed

- `$this->setError(...)` calls that are **not** immediately followed by `return false` are left untouched.
- `return false` statements that are **not** immediately preceded by `$this->setError(...)` are left untouched.
- The message argument is passed through unchanged — variable expressions, concatenations, and translation calls are all preserved.

### Configuration

The rule requires no configuration parameters.

```php
// rector.php
use Joomla\Rector\Joomla6\SetErrorToExceptionRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(SetErrorToExceptionRector::class);
};
```
