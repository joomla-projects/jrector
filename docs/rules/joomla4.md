# Joomla 4 rules

Rules that bring code up to Joomla 4.

Back to the [rule index](../rules.md).

- [JimportRector](#jimportrector)

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
