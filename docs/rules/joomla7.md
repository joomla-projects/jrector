# Joomla 7 rules

Rules that bring code up to Joomla 7.

Back to the [rule index](../rules.md).

- [GetInstanceToContainerRector](#getinstancetocontainerrector)
- [ResultIsFalseablePropertyRector](#resultisfalseablepropertyrector)

---

## GetInstanceToContainerRector

**Class:** `Joomla\Rector\Joomla7\GetInstanceToContainerRector`

Replaces the static `getInstance()` accessors that Joomla 7 removes with what their deprecation notices named: the running application for the pathway and the session, the cache controller factory from the container for the cache. The removal is documented in the [Joomla 7 update notes](https://manual.joomla.org/updates/64-70/removed-backward-incompatibility/).

### Mapping

| Removed call | Replacement |
|--------------|-------------|
| `Pathway::getInstance($client)` | `Factory::getApplication()->getPathway()` |
| `Session::getInstance()` | `Factory::getApplication()->getSession()` |
| `Cache::getInstance($type, $options)` | `Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController($type, $options)` |
| `CacheController::getInstance($type, $options)` | the same |

The arguments of the cache calls are passed on unchanged; both methods default to the `output` controller. `Pathway::getInstance()` named a client, the replacement is the pathway of the running application, so that argument is dropped.

Inside a class that has `getApplication()` (a `CMSPlugin` subclass, an own method, or anything reflection can confirm) the application comes from `$this->getApplication()` instead of the Factory, except in static methods. The old `JPathway`, `JSession`, `JCache` and `JCacheController` aliases are recognised as well.

### Before / After

```php
// Before
class PlgSystemExample extends CMSPlugin
{
    public function onAfterRoute(): void
    {
        $token = Session::getInstance()->getFormToken();
        $cache = Cache::getInstance('output', $options);
    }
}
```

```php
// After
class PlgSystemExample extends CMSPlugin
{
    public function onAfterRoute(): void
    {
        $token = $this->getApplication()->getSession()->getFormToken();
        $cache = \Joomla\CMS\Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class)->createCacheController('output', $options);
    }
}
```

The replacement classes are written fully qualified. Enable Rector's `importNames()` to turn them into `use` statements.

### What is NOT changed

- `getInstance()` of any other class, such as `Table::getInstance()` (see `TableGetInstanceRector`) or `Router::getInstance()`, which Joomla 7 still has.
- A class of the same short name in another namespace, e.g. `Acme\Session::getInstance()`: the namespaced Joomla classes are matched by their resolved name, only the `J*` aliases by their short name.
- `CacheController::addIncludePath()`. Joomla 7 removes it without replacement; a custom cache controller is registered through a cache controller factory instead.

### Manual follow-up

- A `Pathway::getInstance('site')` called from administrator code returned the site pathway; the replacement returns the running application's. Review such calls.

### Configuration

The rule needs no configuration. `autoloadPaths()` is only needed when `getApplication()` is inherited from a class other than `CMSPlugin`.

```php
// rector.php
use Joomla\Rector\Joomla7\GetInstanceToContainerRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(GetInstanceToContainerRector::class);
};
```

---

## ResultIsFalseablePropertyRector

**Class:** `Joomla\Rector\Joomla7\ResultIsFalseablePropertyRector`

Removes the `$resultIsFalseable` property from event classes that use one of the `ResultType*Aware` traits (Array, Float, Integer, Numeric, Object, String).

Joomla 7 removes the flag, `@deprecated 4.3 will be removed in 7.0`, from the traits together with the branch in `typeCheckResult()` that honoured it, see the [Joomla 7 update notes](https://manual.joomla.org/updates/64-70/removed-backward-incompatibility/). An override in an event class is dead code from then on: a `false` result throws the type's `InvalidArgumentException` whatever the property says.

### Before / After

```php
// Before
class GetItemsEvent extends AbstractEvent implements ResultAwareInterface
{
    use ResultAware;
    use ResultTypeArrayAware;

    protected $resultIsFalseable = true;
}
```

```php
// After
class GetItemsEvent extends AbstractEvent implements ResultAwareInterface
{
    use ResultAware;
    use ResultTypeArrayAware;
}
```

The trait use may be declared on the class itself or inherited from a parent class.

### What is NOT changed

- Classes that use none of the six traits, including classes with the Boolean or Mixed trait, which never had the flag. A property of that name there is the class's own.
- Other properties in the same grouped declaration; only the single item is dropped.
- Reads of `$this->resultIsFalseable` in an overridden `typeCheckResult()`.

### Manual follow-up

- An event whose listeners returned `false` to mean "no result" needs `$resultIsNullable = true` and listeners that return `null`, or an exception for a failure. The rule cannot decide that for you.

### Configuration

The rule needs no configuration. `autoloadPaths()` is needed when the trait use is inherited from a parent class, because that is resolved through PHPStan reflection.

```php
// rector.php
use Joomla\Rector\Joomla7\ResultIsFalseablePropertyRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(ResultIsFalseablePropertyRector::class);
};
```
