# Joomla 3 rules

Rules that clean up Joomla 3 code and prepare a component for the namespaced Joomla 4 structure.

Back to the [rule index](../rules.md).

- [HtmlViewToBaseHtmlViewRector](#htmlviewtobasehtmlviewrector)
- [ViewAssignRefToPropertyRector](#viewassignreftopropertyrector)

---

## HtmlViewToBaseHtmlViewRector

**Class:** `Joomla\Rector\Joomla3\MVC\HtmlViewToBaseHtmlViewRector`

Rewrites the inheritance of `Joomla\CMS\MVC\View\HtmlView` to use an aliased import, which is the Joomla 4+ component coding convention.

The rule handles two forms of the parent-class reference:

- **Short name with an existing `use` statement** — adds `as BaseHtmlView` to the existing import and changes `extends HtmlView` to `extends BaseHtmlView`.
- **Fully-qualified class name** — adds a new `use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;` statement before the class and changes `extends \Joomla\CMS\MVC\View\HtmlView` to `extends BaseHtmlView`.

Files where the import already carries the `as BaseHtmlView` alias are left untouched.

### Before / After

Short-name form:

```php
// Before
use Joomla\CMS\MVC\View\HtmlView;

class DefaultView extends HtmlView
{
    public function display($tpl = null): void
    {
        parent::display($tpl);
    }
}
```

```php
// After
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class DefaultView extends BaseHtmlView
{
    public function display($tpl = null): void
    {
        parent::display($tpl);
    }
}
```

Fully-qualified form:

```php
// Before
class DefaultView extends \Joomla\CMS\MVC\View\HtmlView
{
    public function display($tpl = null): void
    {
        parent::display($tpl);
    }
}
```

```php
// After
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class DefaultView extends BaseHtmlView
{
    public function display($tpl = null): void
    {
        parent::display($tpl);
    }
}
```

### What is NOT changed

- Classes that extend a different view class are skipped entirely.
- Files where the import already uses `as BaseHtmlView` are skipped (idempotent).

### Configuration

The rule requires no configuration parameters.

```php
// rector.php
use Joomla\Rector\Joomla3\MVC\HtmlViewToBaseHtmlViewRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(HtmlViewToBaseHtmlViewRector::class);
};
```

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
