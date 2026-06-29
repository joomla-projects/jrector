# Converting a Joomla 3 component to Joomla 4+ namespaces

In the last few minor versions of Joomla 3, the project started switching to namespaced code and an auto-loadable PSR-4-compliant extension structure. With Joomla 4, this is now the preferred structure and this part of the Rector rules are trying to provide a simpler way to transform your existing component into that new structure.

This part is heavily based on the excellent work of [Nicholas K. Dionysopoulos](https://www.akeeba.com/) [Component Upgrader](https://github.com/nikosdion/joomla_com_upgrader). Thank you very much both for the actual code and also the inspiration to extend this further.

## Requirements

It is assumed that you are already using `git`, have a PHP development environment ready and installed both Rector and this library as described [here](index.md).

We strongly recommend to have your components code in a subfolder of your repository. Inside that folder, your component project must have the structure described below.

* Your component's backend code must be in a folder named `administrator`, `admin`, `backend` or `administrator/components/com_yourcomponent` (where `com_yourcomponent` is the name of your component).

* Your component's frontend code must be in a folder named `site`, `frontend`, or `components/com_yourcomponent` (where `com_yourcomponent` is the name of your component).

* Your component's media files must be in a folder named `media`, or `media/com_yourcomponent` (where `com_yourcomponent` is the name of your component).

## What can this tool do for me?

**What it already does**
* Namespace all of your MVC (Model, Controller, View and Table) classes and place them into the appropriate directories.
* Refactor and namespace helper classes (e.h. ExampleHelper, ExampleHelperSomething, etc).
* Refactor and namespace HTML helper classes (e.g. JHtmlExample) into HTML services.
* Refactor and namespace custom form field classes (e.g. JFormFieldExample, JFormFieldModal_Example, etc).
* Refactor and namespace custom form rule classes (e.g. JFormRuleExample).
* Change static type hints in PHP code and docblocks.

**What it CAN NOT and WILL NOT do**
* Remove your old entry point file, possibly converting it to a custom Dispatcher. This is impossible. It requires understanding what your component does and make informed decisions on refactoring.
* Refactor your frontend SEF URL Router.
* Create a custom component extension class to register Html, Category, Router, Tags etc. services. This requires knowing how your component works.

In short, this tool tries to do the 30% of the migration work which would have taken you 70% of the time. Instead of spending _days, or weeks,_ or repetitive, boring, error–prone, soul–crushing grind you spend less than half an hour to read this README, set up Rector and another minute or so to automate all that mind–boggling drudgery.

## Prepare configuration

We are assuming that you are using the `rector.php` in the `assets` directory. If not, you can also copy the necessary parts down below:
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
The whole process will happen in two steps. The first step is refactoring the code and the second step is moving the files to the new locations. For this second step, Rector will create a `rename.php`, which is also why we are disabling parallel execution for Rector for these rules:
```php
    // Disable parallel processing so RenamedClassHandlerService and FileRenameCollectorService
    // are only instantiated once and their __destruct() writes are not overwritten by other workers.
    $rectorConfig->disableParallel();
```
The next step is setting up helper objects to collect the mapping from old to new classes and from old to new filenames:
```php
    $rectorConfig->singleton(RenamedClassHandlerService::class, static function () {
        return new RenamedClassHandlerService(__DIR__);
    });

    $rectorConfig->singleton(FileRenameCollectorService::class);
```
Now we are setting up which class prefixes we want to process. Your "old" component will have classes like `HelloworldModelDashboard` and `Helloworld` would be the prefix here:
```php
    // Configure the namespace mappings
    $joomlaNamespaceMaps = [
        new JoomlaLegacyPrefixToNamespace('Helloworld', 'Acme\HelloWorld', []),
        new JoomlaLegacyPrefixToNamespace('HelloWorld', 'Acme\HelloWorld', []),
    ];
```
We now want to map from that prefix to your new namespace prefix, which is the second parameter here. It is recommended to use the convention `CompanyName\ComponentNameWithoutCom` or `CompanyName\Component\ComponentNameWithoutCom` for your namespace prefix.

**CAUTION!** Note that there aretwo lines here with the legacy Joomla 3 namespace being `Helloworld` in one and `HelloWorld` in another. That is because in Joomla 3 the case of the prefix of your component does not matter. `Helloworld`, `HelloWorld` and `HELLOWORLD` would work just fine. The code refactoring rules are, however, case–sensitive. As a result you need to add as many lines as you have different cases in your component.

The third argument, the empty array `[]`, is a list of class names which begin with the old prefix that you do not want to namespace. I can't think of a reason why you want to do that but I can neither claim I can think of any use case. So I added that option _just in case_ you need it.

Now we add the rules we want to use:
```php
    $rectorConfig->ruleWithConfiguration(HelpersToJ4Rector::class, $joomlaNamespaceMaps);
    $rectorConfig->ruleWithConfiguration(HtmlHelpersRector::class, $joomlaNamespaceMaps);
    $rectorConfig->ruleWithConfiguration(FormFieldsRector::class, $joomlaNamespaceMaps);
    $rectorConfig->ruleWithConfiguration(FormRulesRector::class, $joomlaNamespaceMaps);
    $rectorConfig->ruleWithConfiguration(LegacyMVCToJ4Rector::class, $joomlaNamespaceMaps);
    $rectorConfig->rule(ViewsTmplMoveRector::class);
    $rectorConfig->rule(HtmlViewToBaseHtmlViewRector::class);
```
Here a short list of what each rule does:
- HelpersToJ4Rector, HtmlHelpersRector, FormFieldsRector, FormRulesRector: Converts the respective class of the component into the namespaced variant
- LegacyMVCToJ4Rector: Converts models, views and controllers into the respective namespaced variant and replaces the usage in all code with the new identifier.
- ViewsTmplMoveRector: Prepares all the layout files of a view to be moved to the new, correct place in the component structure.
- HtmlViewToBaseHtmlViewRector: Adds the `BaseHtmlView` alias for `Joomla\CMS\MVC\View\HtmlView` into view classes and changes the base class to this new alias. This prevents issues like `class HtmlView extends HtmlView` when later shortening the class names.

## How to use
We now have prepared our configuration for Rector and can start with the actual refactoring. The first step is to do a dry run to see what Rector is going to change. Simply call `vendor/bin/rector --dry-run --clear-cache` to see the results. If you are happy with those changes, you can run it again without the `--dry-run` parameter.

You should now go through all the changes and commit them to your git repository. At this point, the content of the files has been modified, but they files are still in their original place. We commit now so that git is guaranteed to keep the relation between the old and new file in its changelog. If we change the files AND move them, git (or more specifically the git viewer you are using) might not recognise that the moved file with changed content is the old file just with updates, but that we deleted the old files and added a bunch of new files, thus making you lose the history of your file.

This first pass also created a `src/rename.php`, which you can call with `php src/rename.php` in your projects root. That `rename.php` contains a list of all the old and new files and moves them to the right places. Again, commit your changes!

Congratulations, you now have converted large parts of your component to the new structure. While this is generally not the case, you should now remove those rules again from your rector.php before you continue with other refactorings.
