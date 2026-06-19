# Joomla Rector Rules

Custom [Rector](https://getrector.com/) rules for upgrading Joomla extensions from old Joomla versions up to the Joomla 7.

## Table of Contents

- Joomla 4
  - [JimportRector](#jimportrector)
- Joomla 5
  - [HtmlViewGetToModelGetRector](#htmlviewgettomodelgetrector)
  - [ViewThisTypehintRector](#viewthistypehintrector)

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

The rule only applies to classes whose short name is exactly `HtmlView`. Other classes that happen to call `$this->get()` are left untouched.

You should typehint the correct model by manually adding `/** @var ActualModelClass $model */` to the code as well.

### Before / After

```php
// Before
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
class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $model      = $this->getModel();
        $items      = $model->getItems();
        $pagination = $model->getPagination();
    }
}
```

When `$model` is already defined in the method body, the `$model = $this->getModel()` line is **not** added again:

```php
// Before
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
class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $items = $model->getItems();
    }
}
```

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
