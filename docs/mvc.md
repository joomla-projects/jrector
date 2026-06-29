# Converting a Joomla 3 component to Joomla 4+ namespaces

In the last few minor versions of Joomla 3, the project started switching to namespaced code and a PSR-4-compliant, auto-loadable extension structure. With Joomla 4, this is the preferred structure, and this part of the Rector rules provides a way to transform your existing component into it automatically.

This work is heavily based on the excellent [Component Upgrader](https://github.com/nikosdion/joomla_com_upgrader) by [Nicholas K. Dionysopoulos](https://www.akeeba.com/). Thank you for both the code and the inspiration to extend it further.

## Requirements

It is assumed that you are already using `git`, have a PHP development environment ready, and have installed both Rector and this library as described [here](index.md).

We strongly recommend keeping your component's code in a subfolder of your repository. Inside that folder, your component project must have the structure described below.

- Your component's backend code must be in a folder named `administrator`, `admin`, `backend`, or `administrator/components/com_yourcomponent` (where `com_yourcomponent` is the name of your component).
- Your component's frontend code must be in a folder named `site`, `frontend`, or `components/com_yourcomponent`.
- Your component's media files must be in a folder named `media` or `media/com_yourcomponent`.

## What can this tool do for me?

**What it already does**

- Namespace all of your MVC (Model, Controller, View, and Table) classes and place them into the appropriate directories.
- Refactor and namespace helper classes (e.g. `ExampleHelper`, `ExampleHelperSomething`).
- Refactor and namespace HTML helper classes (e.g. `JHtmlExample`) into HTML services.
- Refactor and namespace custom form field classes (e.g. `JFormFieldExample`, `JFormFieldModal_Example`).
- Refactor and namespace custom form rule classes (e.g. `JFormRuleExample`).
- Update static type hints in PHP code and docblocks.

**What it cannot and will not do**

- Remove your old entry point file or convert it to a custom Dispatcher. This requires understanding what your component does and making informed architectural decisions.
- Refactor your frontend SEF URL router.
- Create a custom component extension class to register HTML, Category, Router, and Tags services. This requires knowing how your component works.

In short, this tool tries to do the 30% of the migration work that would otherwise take 70% of the time. Instead of spending days or weeks on repetitive, error-prone grind, you spend less than half an hour reading this guide and another minute or so automating all that tedious work.

## Prepare configuration

We assume that you are using the `rector.php` from the `assets` directory. If not, you can copy the necessary parts from below:

```php
// MVC refactoring rules
// Disable parallel processing so RenamedClassHandlerService and FileRenameCollectorService
// are only instantiated once and their __destruct() writes are not overwritten by other workers.
$rectorConfig->disableParallel();

$rectorConfig->singleton(RenamedClassHandlerService::class, static function () {
    return new RenamedClassHandlerService(__DIR__);
});

$rectorConfig->singleton(FileRenameCollectorService::class);

// Configure the namespace mappings
$joomlaNamespaceMaps = [
    new JoomlaLegacyPrefixToNamespace('Helloworld', 'Acme\HelloWorld', []),
];

$rectorConfig->ruleWithConfiguration(HelpersToJ4Rector::class, $joomlaNamespaceMaps);
$rectorConfig->ruleWithConfiguration(HtmlHelpersRector::class, $joomlaNamespaceMaps);
$rectorConfig->ruleWithConfiguration(FormFieldsRector::class, $joomlaNamespaceMaps);
$rectorConfig->ruleWithConfiguration(FormRulesRector::class, $joomlaNamespaceMaps);
$rectorConfig->ruleWithConfiguration(LegacyMVCToJ4Rector::class, $joomlaNamespaceMaps);
$rectorConfig->rule(ViewsTmplMoveRector::class);
$rectorConfig->rule(HtmlViewToBaseHtmlViewRector::class);
```

The whole process happens in two steps: first Rector refactors the code, then a generated script moves the files to their new locations. This is why parallel execution must be disabled:

```php
// Disable parallel processing so RenamedClassHandlerService and FileRenameCollectorService
// are only instantiated once and their __destruct() writes are not overwritten by other workers.
$rectorConfig->disableParallel();
```

The next step registers the helper services that collect the mapping from old to new class names and from old to new file paths:

```php
$rectorConfig->singleton(RenamedClassHandlerService::class, static function () {
    return new RenamedClassHandlerService(__DIR__);
});

$rectorConfig->singleton(FileRenameCollectorService::class);
```

Now configure which class prefixes to process. Your legacy component will have classes like `HelloworldModelDashboard`, where `Helloworld` is the prefix:

```php
// Configure the namespace mappings
$joomlaNamespaceMaps = [
    new JoomlaLegacyPrefixToNamespace('Helloworld', 'Acme\HelloWorld', []),
    new JoomlaLegacyPrefixToNamespace('HelloWorld', 'Acme\HelloWorld', []),
];
```

The second argument is your new namespace prefix. The convention `CompanyName\ComponentNameWithoutCom` or `CompanyName\Component\ComponentNameWithoutCom` is recommended.

**Note:** Two lines are used here — one with `Helloworld` and one with `HelloWorld`. In Joomla 3 the casing of a component prefix does not matter, but the Rector rules are case-sensitive. Add one entry for every distinct casing that appears in your component's class names.

The third argument (the empty array `[]`) is an optional list of class names — beginning with the old prefix — that should not be namespaced.

The rules themselves:

```php
$rectorConfig->ruleWithConfiguration(HelpersToJ4Rector::class, $joomlaNamespaceMaps);
$rectorConfig->ruleWithConfiguration(HtmlHelpersRector::class, $joomlaNamespaceMaps);
$rectorConfig->ruleWithConfiguration(FormFieldsRector::class, $joomlaNamespaceMaps);
$rectorConfig->ruleWithConfiguration(FormRulesRector::class, $joomlaNamespaceMaps);
$rectorConfig->ruleWithConfiguration(LegacyMVCToJ4Rector::class, $joomlaNamespaceMaps);
$rectorConfig->rule(ViewsTmplMoveRector::class);
$rectorConfig->rule(HtmlViewToBaseHtmlViewRector::class);
```

- `HelpersToJ4Rector`, `HtmlHelpersRector`, `FormFieldsRector`, `FormRulesRector` — convert the respective legacy class type into its namespaced Joomla 4 variant.
- `LegacyMVCToJ4Rector` — converts models, views, controllers, and tables into their namespaced variants and updates all references across the codebase.
- `ViewsTmplMoveRector` — registers all view layout files so they will be moved from `views/<view>/tmpl/` to `tmpl/<view>/` by the generated rename script.
- `HtmlViewToBaseHtmlViewRector` — adds the `BaseHtmlView` alias for `Joomla\CMS\MVC\View\HtmlView` in view classes and updates the `extends` clause accordingly. This prevents the `class HtmlView extends HtmlView` collision that would otherwise occur when shortening class names later.

## How to use

With the configuration ready, start with a dry run to preview what Rector will change:

```
vendor/bin/rector --dry-run --clear-cache
```

If you are happy with the results, run it without `--dry-run`.

Review all changes and commit them to your git repository before going further. At this point the file contents have been modified but the files are still in their original locations. Committing now ensures that git tracks the connection between old and new files. If you change the file contents and move them in the same commit, git may not recognise that the moved file is the same as the old one, and you will lose the file history.

The first pass also generates a `src/rename.php` file. Run it from your project root:

```
php src/rename.php
```

This script moves all files to their new locations. Commit these changes as well.

Congratulations — you have now converted the majority of your component to the new Joomla 4 structure. Unless other rules depend on the MVC rules, remove them from `rector.php` before continuing with further refactoring steps.
