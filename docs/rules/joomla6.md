# Joomla 6 rules

Rules that bring code up to Joomla 6.

Back to the [rule index](../rules.md).

- [AllowLegacyListenersRector](#allowlegacylistenersrector)
- [CmsObjectReturnTypeRector](#cmsobjectreturntyperector)
- [CountModulesRector](#countmodulesrector)
- [DispatcherGetLayoutDataRector](#dispatchergetlayoutdatarector)
- [DocumentAssetsToWebAssetManagerRector](#documentassetstowebassetmanagerrector)
- [EventArgumentsToTypedEventRector](#eventargumentstotypedeventrector)
- [FactoryGetDocumentRector](#factorygetdocumentrector)
- [HandlerReturnToEventResultRector](#handlerreturntoeventresultrector)
- [HtmlViewExceptionHandlingRector](#htmlviewexceptionhandlingrector)
- [JpathPlatformToJexecRector](#jpathplatformtojexecrector)
- [LegacyHandlerSignatureRector](#legacyhandlersignaturerector)
- [ModuleHelperStaticToHelperFactoryRector](#modulehelperstatictohelperfactoryrector)
- [ModuleTmplTypehintRector](#moduletmpltypehintrector)
- [SetErrorToExceptionRector](#seterrortoexceptionrector)
- [TemplateThisTypehintRector](#templatethistypehintrector)

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

## AllowLegacyListenersRector

**Class:** `Joomla\Rector\Joomla6\Plugin\AllowLegacyListenersRector`

Cleans up the deprecated `$allowLegacyListeners` property in plugins that implement `SubscriberInterface`.

Verified against Joomla 6.1.1, `libraries/src/Plugin/CMSPlugin.php`. `registerListeners()` begins with:

```php
if ($this instanceof SubscriberInterface) {
    $this->getDispatcher()->addSubscriber($this);

    return;
}
```

For a plugin implementing `SubscriberInterface` the property is therefore **never read at all**. On top of that, the property is marked `@deprecated 4.3 will be removed in 7.0` and `registerListeners()` itself `@deprecated 5.4.0 will be removed in 7.0`.

This is why the default mode is `remove` rather than `set_false`: on a subscriber plugin, setting the property to `false` only adds a deprecated declaration that has no effect and will have to be deleted again before Joomla 7.

### Before / After

```php
// Before
class PlgContentExample extends CMSPlugin implements SubscriberInterface
{
    protected $allowLegacyListeners = true;

    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare'];
    }
}
```

```php
// After
class PlgContentExample extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare'];
    }
}
```

### What is NOT changed

- Classes that do not implement `SubscriberInterface`, directly or inherited. There the property is still read, and removing it would change behaviour.
- Classes that do not extend `CMSPlugin`.
- Other properties in the same grouped declaration — only the single property item is dropped.
- The visibility modifier of an existing property in `set_false` mode.

### Configuration

Runs sensibly after `PluginSubscriberInterfaceRector` (Joomla 5), which adds the interface in the first place.

```php
// rector.php
use Joomla\Rector\Joomla6\Plugin\AllowLegacyListenersRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    // Default: remove the property.
    $rectorConfig->rule(AllowLegacyListenersRector::class);

    // Alternative: keep it, but force it to false.
    $rectorConfig->ruleWithConfiguration(AllowLegacyListenersRector::class, [
        AllowLegacyListenersRector::MODE => AllowLegacyListenersRector::MODE_SET_FALSE,
    ]);
};
```

`autoloadPaths()` is only needed when the plugin inherits `SubscriberInterface` or `CMSPlugin` through an intermediate class, because that chain is resolved through PHPStan reflection.

---

## DispatcherGetLayoutDataRector

**Class:** `Joomla\Rector\Joomla6\Module\DispatcherGetLayoutDataRector`

Moves the logic of a hand written module `dispatch()` method into `getLayoutData()`. After a purely structural conversion the whole legacy module body usually still sits in `dispatch()`, including `require ModuleHelper::getLayoutPath(...)`. The intended split is that `getLayoutData()` returns the data and `AbstractModuleDispatcher::dispatch()` renders the layout — only then do layout overrides and module caching work reliably.

The rule renames the method, makes it `protected`, gives it an `array` return type, inserts `$data = parent::getLayoutData();`, drops the layout `require`/`include`, and appends `return $data;`.

### Before / After

```php
// Before
class Dispatcher extends AbstractModuleDispatcher
{
    public function dispatch()
    {
        $params = $this->module->params;
        $items  = $this->getItems($params);

        require ModuleHelper::getLayoutPath('mod_foo', $params->get('layout', 'default'));
    }
}
```

```php
// After
class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData(): array
    {
        // TODO jrector: move every local variable the layout uses into $data, e.g. $data['items'] = $items;
        $data = parent::getLayoutData();
        $params = $this->module->params;
        $items  = $this->getItems($params);

        return $data;
    }
}
```

### What is NOT changed

- Classes that already have a `getLayoutData()` method.
- `dispatch()` methods without a `ModuleHelper::getLayoutPath()` include — those modules deliberately output directly.
- Dispatchers implementing `DispatcherInterface` directly instead of extending `AbstractModuleDispatcher`; they have no `parent::getLayoutData()`.
- Local variables in the body. The rule cannot know which of them the layout expects, so it does not move them into `$data` and marks the method with a TODO instead. The aggressive alternative — copying every local variable into `$data` — was rejected because it invents array keys the layout may never use while still not guaranteeing the ones it does.
- The content of the layout files under `tmpl/`.

### Manual follow-up

- Every variable the layout expects has to end up in `$data`. The rule marks the spot; it does not resolve it.
- The `use Joomla\CMS\Helper\ModuleHelper;` import usually becomes unused — Rector's `RemoveUnusedImportsRector` cleans that up.

### Configuration

```php
// rector.php
use Joomla\Rector\Joomla6\Module\DispatcherGetLayoutDataRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(DispatcherGetLayoutDataRector::class);
};
```

`autoloadPaths()` is only needed when the dispatcher inherits from `AbstractModuleDispatcher` through an intermediate class.

---

## HandlerReturnToEventResultRector

**Class:** `Joomla\Rector\Joomla6\Plugin\HandlerReturnToEventResultRector`

Writes a plugin handler's return value into the event result instead of returning it. When a handler is invoked through the event dispatcher its return value is discarded, so a `return true;` silently stops working — it does not crash, it just has no effect. That makes it one of the nastier migration bugs.

The canonical method is `addResult()`, verified against Joomla 6.1.1: it is declared in `libraries/src/Event/Result/ResultAwareInterface.php` and implemented by the `ResultAware` trait in the same folder. `updateEventResult()` exists on exactly one event (`Plugin\AjaxEvent`) and is therefore not the generic form.

Because `addResult()` only exists on events implementing `ResultAwareInterface`, the rule only converts handlers whose event class is marked result aware in the generated event map (22 of the 127 core events). Rewriting any other handler would produce a call to a method that does not exist.

### Before / After

```php
// Before
public function onUserAuthenticate(AuthenticationEvent $event)
{
    $credentials = $event->getCredentials();

    if (!$this->check($credentials)) {
        return false;
    }

    return true;
}
```

```php
// After
public function onUserAuthenticate(AuthenticationEvent $event): void
{
    $credentials = $event->getCredentials();

    if (!$this->check($credentials)) {
        $event->addResult(false);
        return;
    }

    $event->addResult(true);
}
```

A trailing `return <expr>;` becomes a bare `addResult()` call, because the method ends there anyway.

### What is NOT changed

- `return` without a value.
- Methods without a resolvable event parameter, or whose event is not result aware.
- Classes that do not extend `CMSPlugin`.
- Methods that are not event handlers, i.e. no `on` prefix and no entry in `getSubscribedEvents()` — ordinary helper methods are never touched.
- Handlers that already call `addResult()` or `updateEventResult()` on the event parameter.
- `return` inside closures or anonymous functions in the method body.

### Manual follow-up

- Some events expect a setter on the event object rather than a result array. `addResult()` is the generic form and should be checked per event against the Joomla documentation.

### Configuration

```php
// rector.php
use Joomla\Rector\Joomla6\Plugin\HandlerReturnToEventResultRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(HandlerReturnToEventResultRector::class);
};
```

Custom event classes are registered through `EVENT_ARGUMENT_MAP` exactly as for `EventArgumentsToTypedEventRector`; add `'resultAware' => true` to the definition so the rule knows the event supports `addResult()`.

---

## LegacyHandlerSignatureRector

**Class:** `Joomla\Rector\Joomla6\Plugin\LegacyHandlerSignatureRector`

Converts a legacy plugin handler with individual parameters to the event object signature. Joomla 6 calls event handlers with an event object; the old signature with individual and reference parameters only worked through the legacy listener layer, which disappears together with the legacy listeners.

The method body is left untouched. The original parameter names are restored as local variables at the top of the method, so the diff stays reviewable and the rule stays small.

The event class is resolved from the method name — or from `getSubscribedEvents()` when the method name differs — through the generated event name map, which is `CoreEventAware::$eventNameToConcreteClass` from the Joomla core.

### Before / After

```php
// Before
class PlgContentExample extends CMSPlugin
{
    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        if ($context !== 'com_content.article') {
            return;
        }

        $article->text .= '<p>foo</p>';
    }
}
```

```php
// After
class PlgContentExample extends CMSPlugin
{
    public function onContentPrepare(\Joomla\CMS\Event\Content\ContentPrepareEvent $event): void
    {
        $context = $event->getContext();
        $article = $event->getItem();
        $params = $event->getParams();
        $page = $event->getPage();
        if ($context !== 'com_content.article') {
            return;
        }

        $article->text .= '<p>foo</p>';
    }
}
```

The event class is written fully qualified. Enable Rector's `importNames()` — the example configuration in `assets/rector.php` does — to turn it into a `use` statement.

### What is NOT changed

- Methods that already take an event object.
- Methods whose event name is not resolvable through the event name map.
- Classes that do not extend `CMSPlugin`.
- The method body, apart from the inserted assignments.
- Handlers with more parameters than the event has known arguments. Fewer parameters are fine — a prefix of the argument list is converted.
- **Reference parameters that are reassigned in the body.** `&$article` followed by `$article = 'x';` writes the value back to the caller, and a getter cannot do that. Such handlers are skipped entirely. Note this is stricter than only skipping scalars: assigning a whole new object to a reference parameter loses the write-back just the same.
- The return type, when the handler still returns a value — that is the job of `HandlerReturnToEventResultRector`.

### Manual follow-up

- Handlers with a return value additionally need `HandlerReturnToEventResultRector`.
- Skipped handlers with reassigned reference parameters have to be converted by hand, to `$event->setArgument(...)` or the matching setter of the event class.
- The rule assumes the plugin implements `SubscriberInterface`; `PluginSubscriberInterfaceRector` (Joomla 5) adds it.

### Recommended order

1. `PluginSubscriberInterfaceRector` (Joomla 5) — declares the handlers explicitly.
2. `LegacyHandlerSignatureRector` — gives the handlers an event parameter.
3. `EventArgumentsToTypedEventRector` — rewrites argument access inside the handlers.
4. `HandlerReturnToEventResultRector` — moves return values into the event result.
5. `AllowLegacyListenersRector` — drops the then dead legacy listener property.

Steps 2 and 3 depend on each other: the argument rule only touches handlers that already have a typed event parameter, which is exactly what the signature rule produces.

### Configuration

```php
// rector.php
use Joomla\Rector\Joomla6\Plugin\LegacyHandlerSignatureRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->ruleWithConfiguration(LegacyHandlerSignatureRector::class, [
        // Own event classes, same notation as EventArgumentsToTypedEventRector
        LegacyHandlerSignatureRector::EVENT_ARGUMENT_MAP => [
            \Acme\Event\MyCustomEvent::class => ['context', 'item'],
        ],
        // Own event name => event class entries
        LegacyHandlerSignatureRector::EVENT_NAME_MAP => [
            'onAcmeSomething' => \Acme\Event\MyCustomEvent::class,
        ],
    ]);
};
```

---

## ModuleHelperStaticToHelperFactoryRector

**Class:** `Joomla\Rector\Joomla6\Module\ModuleHelperStaticToHelperFactoryRector`

Moves module helpers from static calls onto the `HelperFactory`. Module helpers are obtained through the DI container; static helpers cannot be tested, overridden, or registered in the service provider.

The rule covers both sides of the change in one pass, on purpose: in a dispatcher it rewrites the call site and adds `HelperFactoryAwareInterface` plus `HelperFactoryAwareTrait`, and in the helper class it turns `public static function` into `public function` and `self::`/`static::` into `$this->`. Splitting this into two rules would allow running only one half, and each half alone breaks the code — converted call sites against a still-static helper only raise a deprecation, but a converted helper still called statically is a fatal error.

### Before / After

```php
// Before — dispatcher
class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData(): array
    {
        $data          = parent::getLayoutData();
        $data['items'] = FooHelper::getItems($data['params'], $this->getApplication());

        return $data;
    }
}
```

```php
// After — dispatcher
class Dispatcher extends AbstractModuleDispatcher implements \Joomla\CMS\Helper\HelperFactoryAwareInterface
{
    use \Joomla\CMS\Helper\HelperFactoryAwareTrait;
    protected function getLayoutData(): array
    {
        $data          = parent::getLayoutData();
        $data['items'] = $this->getHelperFactory()->getHelper('FooHelper')->getItems($data['params'], $this->getApplication());

        return $data;
    }
}
```

```php
// Before — helper
class FooHelper
{
    public static function getItems($params)
    {
        return self::filter([]);
    }
}
```

```php
// After — helper
class FooHelper
{
    public function getItems($params)
    {
        return $this->filter([]);
    }
}
```

### What is NOT changed

- `Joomla\CMS\Helper\ModuleHelper`, `HTMLHelper` and every other core helper under the `Joomla\` namespace.
- Component helpers, recognised by `\Component\` in the namespace.
- Static calls outside a dispatcher — for example directly in a `tmpl/` file, where `$this` is not a dispatcher. Those are skipped.
- Helper methods that read a static property of their own class; they have to stay static.
- Helper classes with a parent class, where `static::` may need late static binding.
- Call sites that already go through `getHelperFactory()`, and classes that already have the interface or the trait.

### Manual follow-up

- The helper has to be registered in `services/provider.php` through `HelperFactory` with the correct namespace, otherwise `getHelper()` fails at runtime.
- Static calls in layout files have to be resolved by hand — that data belongs in `getLayoutData()`.

### Configuration

```php
// rector.php
use Joomla\Rector\Joomla6\Module\ModuleHelperStaticToHelperFactoryRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(ModuleHelperStaticToHelperFactoryRector::class);
};
```

---

## ModuleTmplTypehintRector

**Class:** `Joomla\Rector\Joomla6\Module\ModuleTmplTypehintRector`

Adds `@var` annotations for the standard module layout variables to files under `mod_<name>/tmpl/`. Module layouts are included from the dispatcher at runtime, so static analysis only sees undefined variables there. The annotations are what make a module analysable at all.

The variable set is taken from `AbstractModuleDispatcher::getLayoutData()` in Joomla 6.1.1 (`libraries/src/Dispatcher/AbstractModuleDispatcher.php`), which returns exactly `module`, `app`, `input`, `params` and `template`. Note this includes `input`, which is easy to miss.

Placement follows `ViewThisTypehintRector`: if a file header docblock exists — recognised by `@package`, `@copyright` or `@license` — the annotations go after it, otherwise before everything.

### Before / After

```php
// Before: mod_foo/tmpl/default.php
<?php
\defined('_JEXEC') or die;

foreach ($items as $item) {
    echo $item->title;
}
```

```php
// After: mod_foo/tmpl/default.php
<?php
/** @var \stdClass $module */
/** @var \Joomla\CMS\Application\CMSApplicationInterface $app */
/** @var \Joomla\Input\Input $input */
/** @var \Joomla\Registry\Registry $params */
/** @var string $template */
/** @var \stdClass[] $items */
\defined('_JEXEC') or die;

foreach ($items as $item) {
    echo $item->title;
}
```

### What is NOT changed

- Files outside a `mod_*/tmpl/` folder. Component layouts also use `tmpl/`, which is why the `mod_` prefixed folder is required. As a fallback a `tmpl/` folder whose parent contains a `mod_*.xml` manifest is accepted.
- Annotations that are already present. Each variable is checked individually, so partially annotated files are completed rather than skipped.
- The rest of the file content.

### Configuration

```php
// rector.php
use Joomla\Rector\Joomla6\Module\ModuleTmplTypehintRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->ruleWithConfiguration(ModuleTmplTypehintRector::class, [
        ModuleTmplTypehintRector::EXTRA_VARIABLES => [
            'items' => '\\stdClass[]',
        ],
    ]);
};
```

`autoloadPaths()` is not required — the rule works purely on file paths.

---

## CountModulesRector

**Class:** `Joomla\Rector\Joomla6\Template\CountModulesRector`

Splits `countModules()` calls that pass a condition string into individual calls.

Verified against Joomla 6.1.1, `libraries/src/Document/HtmlDocument.php`:

```php
public function countModules(string $positionName, bool $withContentOnly = false)
```

The parameter is a plain, strictly typed position name — the expression evaluation is gone entirely. A call such as `countModules('a and b')` therefore no longer counts anything: it silently looks for a position literally named `a and b` and returns `0`. This is not a deprecation you can postpone, it is a behaviour change that fails quietly.

**Deviation from the task specification, on purpose:** the generated calls do *not* pass `true` as the second argument. The old expression form counted with the default `$withContentOnly = false`, so adding `true` would silently switch to "only modules that actually render content" and change which branches of a template are taken. Set `WITH_CONTENT_ONLY` if the stricter counting is what you want.

### Before / After

```php
// Before
if ($this->countModules('sidebar-left and sidebar-right')) {
    echo 'both';
}

$count = $this->countModules('top-a + top-b');
```

```php
// After
if ($this->countModules('sidebar-left') && $this->countModules('sidebar-right')) {
    echo 'both';
}

$count = $this->countModules('top-a') + $this->countModules('top-b');
```

`and` and `or` produce the boolean form, `+` the sum. Operators are matched case insensitively and repeated whitespace is tolerated, so `'a  AND  b'` is parsed correctly.

### What is NOT changed

- Calls with a plain position name and no operator — already the supported form.
- Calls that already pass a second argument.
- Calls whose first argument is not a string literal.
- Expressions with mixed operators (`'a and b or c'`) or parentheses. The intended precedence is not recoverable, so these are skipped.
- Calls on a receiver whose type does not resolve to an `HtmlDocument`. `$this` is accepted at file scope, where a template has no class for PHPStan to work from, but rejected inside an unrelated class that happens to have its own `countModules()`.

### Manual follow-up

- Skipped mixed expressions have to be resolved by hand, making the intended precedence explicit with parentheses.

### Configuration

```php
// rector.php
use Joomla\Rector\Joomla6\Template\CountModulesRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(CountModulesRector::class);

    // Count only modules that actually render content:
    $rectorConfig->ruleWithConfiguration(CountModulesRector::class, [
        CountModulesRector::WITH_CONTENT_ONLY => true,
    ]);
};
```

`autoloadPaths()` is needed for the receiver type check on anything other than `$this`.

---

## DocumentAssetsToWebAssetManagerRector

**Class:** `Joomla\Rector\Joomla6\Template\DocumentAssetsToWebAssetManagerRector`

Moves direct CSS/JS registration from the document onto the WebAssetManager. The old calls bypass the asset registry completely, so ordering and deduplication cannot be controlled.

Verified against Joomla 6.1.1:

- `libraries/src/Document/Document.php` marks `addScript()`, `addScriptDeclaration()`, `addStyleSheet()` and `addStyleDeclaration()` as `@deprecated 4.3 will be removed in 7.0`. `addScriptOptions()` carries no deprecation and is left alone.
- `libraries/src/WebAsset/WebAssetManager.php` resolves `registerAndUseStyle()`, `registerAndUseScript()`, `addInlineStyle()` and `addInlineScript()` through `__call()`, onto `registerAsset(string $type, $asset, string $uri = '', array $options = [], ...)` and `addInline(string $type, $content, ...)`. That is where the argument order `registerAndUseStyle($name, $uri)` comes from.

### Mapping

| Before | After |
|--------|-------|
| `$doc->addStyleSheet($url)` | `$wa->registerAndUseStyle($name, $url)` |
| `$doc->addScript($url)` | `$wa->registerAndUseScript($name, $url)` |
| `$doc->addStyleDeclaration($css)` | `$wa->addInlineStyle($css)` |
| `$doc->addScriptDeclaration($js)` | `$wa->addInlineScript($js)` |
| `HTMLHelper::_('stylesheet', $file)` | `$wa->registerAndUseStyle($name, $file)` |
| `HTMLHelper::_('script', $file)` | `$wa->registerAndUseScript($name, $file)` |

### Asset names

`registerAndUseStyle()` needs a name. It is derived from the path: `media/templates/site/foo/css/template.css` becomes `foo.template`. The same applies to `templates/<name>/` and `media/<extension>/`. Anything that matches no known layout falls back to the file name without extension. Everything is lower cased and reduced to `[a-z0-9._-]`. Use `ASSET_NAME_PREFIX` to put your vendor in front.

### Obtaining `$wa`

`$wa = ...->getWebAssetManager();` is inserted once per scope — per method, or once at file level for a template — directly before the first rewritten call. If `$wa` is already assigned in that scope, nothing is inserted. The source depends on the receiver: `$doc->getWebAssetManager()` for a document variable, `$this->getWebAssetManager()` in a template file where `$this` is the document, and `$this->getDocument()->getWebAssetManager()` otherwise.

### Before / After

```php
// Before
$doc = $this->getDocument();
$doc->addStyleSheet('media/templates/site/foo/css/template.css');
$doc->addScriptDeclaration('console.log("hi");');
```

```php
// After
$doc = $this->getDocument();
$wa = $doc->getWebAssetManager();
$wa->registerAndUseStyle('foo.template', 'media/templates/site/foo/css/template.css');
$wa->addInlineScript('console.log("hi");');
```

### What is NOT changed

- Calls with a dynamic path argument. A name generated from a variable or a concatenation is not reliable.
- `HTMLHelper::_('behavior.*')` and `bootstrap.*`. These need `useScript()` with the correct core asset names and a mapping table of their own — deliberately out of scope.
- `HTMLHelper::_('stylesheet', $file, $options)` with a third argument. The option arrays of `HTMLHelper` and of the WebAssetManager do not mean the same thing, so passing them through would be a guess.
- `addScriptOptions()`, which is not deprecated.
- Calls on receivers whose type does not resolve to a document.
- Already converted code.

### Manual follow-up

- Check the generated asset names and align them with a `joomla.asset.json`. In the long run, declare assets there and only call `useStyle()` / `useScript()`.
- `behavior.*` and `bootstrap.*` calls stay and have to be converted by hand.
- `HTMLHelper` calls with an option array stay and have to be converted by hand.

### Configuration

```php
// rector.php
use Joomla\Rector\Joomla6\Template\DocumentAssetsToWebAssetManagerRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->ruleWithConfiguration(DocumentAssetsToWebAssetManagerRector::class, [
        DocumentAssetsToWebAssetManagerRector::ASSET_NAME_PREFIX => 'acme.',
    ]);
};
```

`autoloadPaths()` is needed so receivers other than a tracked `$doc` variable can be resolved to a document.

---

## FactoryGetDocumentRector

**Class:** `Joomla\Rector\Joomla6\Template\FactoryGetDocumentRector`

Replaces `Factory::getDocument()` with the getter that fits the context. The static access bypasses the DI container and makes code untestable. Despite living in the `Template` namespace, the rule applies to any class — views, plugins and services included.

`Factory::getDocument()`, `JFactory::getDocument()` and `\Joomla\CMS\Factory::getDocument()` are all recognised.

### Mapping by context

The order of the checks is binding, first match wins:

| Context | Replacement |
|---------|-------------|
| Class implements `Joomla\CMS\Document\DocumentAwareInterface`, directly or inherited | `$this->getDocument()` |
| Class has a `getApplication()` method | `$this->getApplication()->getDocument()` |
| A variable in scope holds `Factory::getApplication()` | `$app->getDocument()` |
| otherwise | skipped |

### Before / After

```php
// Before
class ExampleView implements DocumentAwareInterface
{
    public function display($tpl = null)
    {
        $doc = Factory::getDocument();
        $doc->setTitle('Example');
    }
}
```

```php
// After
class ExampleView implements DocumentAwareInterface
{
    public function display($tpl = null)
    {
        $doc = $this->getDocument();
        $doc->setTitle('Example');
    }
}
```

### What is NOT changed

- Calls outside a class when no application variable is in scope, for example directly in a template `index.php`.
- Calls with arguments, such as `Factory::getDocument($type)`.
- Classes matching none of the three contexts. The `getApplication()` check requires proof — an own method, a `CMSPlugin` ancestry, or something reflection can confirm. Guessing would produce a call to a missing method.
- Already converted code.

### Configuration

The rule needs no configuration.

```php
// rector.php
use Joomla\Rector\Joomla6\Template\FactoryGetDocumentRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(FactoryGetDocumentRector::class);
};
```

`autoloadPaths()` is required for the reflection path, i.e. whenever `DocumentAwareInterface` or `getApplication()` is inherited rather than declared on the class itself.

---

## JpathPlatformToJexecRector

**Class:** `Joomla\Rector\Joomla6\JpathPlatformToJexecRector`

Switches the direct access guard from `JPATH_PLATFORM` to `_JEXEC`.

Verified against Joomla 6.1.1: `JPATH_PLATFORM` does not occur once in `libraries/`, so the constant is only available while the backward compatibility plugin is active. A file guarded with it then aborts immediately — and because `die` produces no message, the symptom is a blank page that is unpleasant to trace.

Only the string argument is touched. A leading backslash, the `or` / `||` form and the shape of `die` are all preserved.

### Before / After

```php
// Before
defined('JPATH_PLATFORM') or die;
\defined('JPATH_PLATFORM') or die;
defined('JPATH_PLATFORM') || die();

if (!defined('JPATH_PLATFORM')) {
    die();
}
```

```php
// After
defined('_JEXEC') or die;
\defined('_JEXEC') or die;
defined('_JEXEC') || die();

if (!defined('_JEXEC')) {
    die();
}
```

### What is NOT changed

- Guards already using `_JEXEC`.
- `JPATH_PLATFORM` in path expressions such as `JPATH_PLATFORM . '/src/foo.php'`. There is no generally correct replacement.
- Other `JPATH_*` constants — `JPATH_BASE`, `JPATH_ROOT` and friends are unaffected.
- Statements that check `_JEXEC` **and** `JPATH_PLATFORM` at once. Those are deliberate, so the rule only flags them with a TODO comment instead of guessing.

### Manual follow-up

- Path expressions using `JPATH_PLATFORM` have to be replaced by hand, usually with `JPATH_LIBRARIES`. Enable `MARK_OTHER_USAGES` to have the rule flag them with a TODO comment.

### Configuration

```php
// rector.php
use Joomla\Rector\Joomla6\JpathPlatformToJexecRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->ruleWithConfiguration(JpathPlatformToJexecRector::class, [
        // Also flag path expressions with a TODO comment. Default: false.
        JpathPlatformToJexecRector::MARK_OTHER_USAGES => true,
    ]);
};
```

`autoloadPaths()` is not required — the rule works purely on the AST.

---

## TemplateThisTypehintRector

**Class:** `Joomla\Rector\Joomla6\Template\TemplateThisTypehintRector`

Adds the `@var` annotation for `$this` to Joomla template files. `index.php`, `component.php`, `offline.php` and `error.php` are included from the document object, so `$this` is the document there — but without an annotation neither an IDE nor PHPStan can see that, which makes static analysis of a template pointless.

The type map is verified against Joomla 6.1.1:

| File | Type |
|------|------|
| `index.php` | `\Joomla\CMS\Document\HtmlDocument` |
| `component.php` | `\Joomla\CMS\Document\HtmlDocument` |
| `offline.php` | `\Joomla\CMS\Document\HtmlDocument` |
| `error.php` | `\Joomla\CMS\Document\ErrorDocument` |

`libraries/src/Document/ErrorDocument.php` declares `class ErrorDocument extends HtmlDocument` and sets `$params['file'] = 'error.php'`, so `error.php` gets the more precise `ErrorDocument`. `libraries/src/Application/SiteApplication.php` sets `themeFile` to `offline.php` and `component.php`, both rendered through the HtmlDocument.

A template folder is recognised by a `templateDetails.xml` next to the file. That is more reliable than a path check on `templates/` and also works when the repository contains only the template folder itself.

### Before / After

```php
// Before: templates/foo/index.php
<?php
\defined('_JEXEC') or die;

$app = Factory::getApplication();
```

```php
// After: templates/foo/index.php
<?php
/** @var \Joomla\CMS\Document\HtmlDocument $this */
\defined('_JEXEC') or die;

$app = Factory::getApplication();
```

If a file header docblock is present — recognised by `@package`, `@copyright` or `@license` — the annotation goes after it, otherwise before everything.

### What is NOT changed

- Files without a `templateDetails.xml` in the same directory.
- Files that already carry a `@var $this` annotation.
- Layout overrides under `templates/<name>/html/…`. Those have different `$this` types and are out of scope; they are excluded automatically because no `templateDetails.xml` sits next to them.
- The rest of the file content.

### Configuration

`EXTRA_VARIABLES` adds annotations for further variables — but **only** for variables Joomla actually provides in the template scope, never for ones the template assigns itself. A wrong annotation is worse than none.

```php
// rector.php
use Joomla\Rector\Joomla6\Template\TemplateThisTypehintRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(TemplateThisTypehintRector::class);
};
```

`autoloadPaths()` is not required — the rule works purely on file paths.

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
