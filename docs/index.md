# Documentation for Joomla Rector rules

This is the documentation how to use the Rector rules to update the code of Joomla extensions. The rules try to take over the most tedious work when removing deprecated code and code constructs.

- [Getting started](#getting-started)
- [List of rules](rules.md)
- [How to convert the component MVC structure from Joomla 3 to Joomla 4](mvc.md)

## What is Rector?

Rector is a pretty powerfull tool to convert PHP code based on predefined rules. If you are interested in an indepth read on this, please look [here](https://getrector.com/documentation). Rector is not just a fancy search&replace tool, but goes a lot deeper. It reads your code with the static code analyser `phpstan` and tries to understand your code regardless of how it is formatted or how it is structured. It will for example understand both `$this->test();` and `$this->test ();`, but also match on classes which not just directly, but also indirectly inherit from another class. It can then convert existing code in a quite complex way.

## Getting started

First of all you have to install Rector via composer by calling `composer require --dev rector/rector joomla-projects/jrector joomla-projects/typehints`. After this, you can call it via `vendor/bin/rector` and it will directly start converting your code based on the configuration you set. So don't do this if you are not sure. If you only want to test your current configuration, you can run it with `vendor/bin/rector --dry-run`. This also installs the rules from this repository as well.

## Configuring Rector
To actually do any work, you have to configure Rector with a `rector.php` file in the root of your project or repository. You can find a default rector.php in the `assets` folder, which includes all rules in this repo. If you want to write your own `rectorphp`, follow along. The simplest rector.php file looks like this:
```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
};
```
As you can imagine this isn't really doing anything right now. Lets add the path to our source code to convert:
```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    // Define the path (or paths) to refactor
    $rectorConfig->paths([__DIR__ . '/src']);
};
```
Notice that you are handing over an array of paths, so if your repository does not have all the code in a `/src` folder, you could also just list all folders individually here. For now everything in the given folder will be processed.

Now, Rector already automatically reads all code from the `vendor` folder to learn possible context, but in the case of Joomla, you normally don't have the CMS in the `vendor` folder. So we add a folder with a Joomla installation to provide Rector context for all the Joomla core classes. This code will only be read, not written to by Rector.
```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    // Define the path (or paths) to refactor
    $rectorConfig->paths([__DIR__ . '/src']);

    // Add additional path to load files for context, in this case a copy of Joomla to understand Joomla core classes
    $rectorConfig->autoloadPaths([
        __DIR__ . '/joomla',
    ]);
};
```
Next we want to add the rules to do the actual processing. For us, three ways to add and configure rules are relevant:
```php
<?php

declare(strict_types=1);

use Joomla\Rector\Joomla3\MVC\Config\JoomlaLegacyPrefixToNamespace;
use Joomla\Rector\Joomla3\MVC\LegacyMVCToJ4Rector;
use Joomla\Rector\Joomla4\JimportRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    // Define the path (or paths) to refactor
    $rectorConfig->paths([__DIR__ . '/src']);

    // Add additional path to load files for context, in this case a copy of Joomla to understand Joomla core classes
    $rectorConfig->autoloadPaths([
        __DIR__ . '/joomla',
    ]);

    $rectorConfig->rule(JimportRector::class);

    $joomlaNamespaceMaps = [
        new JoomlaLegacyPrefixToNamespace('Helloworld', 'Acme\HelloWorld', []),
    ];
    $rectorConfig->ruleWithConfiguration(LegacyMVCToJ4Rector::class, $joomlaNamespaceMaps);

        // Basic refactorings
    $rectorConfig->sets([
        // Auto-refactor code to at least PHP 8.1 (minimum Joomla 6 version)
        LevelSetList::UP_TO_PHP_81,
    ]);
};
```
The first is `$rectorConfig->rule(<Classname>);`, adding a single rule without any necessary configuration. `$rectorConfig->ruleWithConfiguration(<Classname>, $config);` on the other hand allows to add additional configuration to the rule, for example a folder or even a whole service object. Last but not least, you can use ready-made sets of rules with `$rectorConfig->sets([]);`.

## How to properly refactor your code
Our Rector now is configured and ready to go. We could run all rules at once and then hope for the best, but that is a pretty good recipe for desaster. Instead we want to do changes in good, bite-sized chunks, making sure that all changes are actually good.

The easiest way is to only ever run one (new) rule at a time. You can simply comment out everything that you are not ready to process yet and then run Rector with just that rule. `vendor/bin/rector` Now you might get a bunch of changes or maybe even none at all and now you should go through all the changes and check them if they are correct. Commit your changes to your version control when you feel you have completed a rule and you are satisfied with the results. Next you uncomment the next rule and let everything run through again. Rules you added previously normally don't have to be removed again, they might even find stuff again which could be a regression from other rules.

The important part is, that Rector is not a fire-and-forget-solution and even if it runs, the chosen rules might make mistakes. You have to check each and every change the tool does to ensure that it actually does what it is supposed to do. That gets pretty complicated when you run 30 rules at once and everything does major changes to your codebase. So please follow the mantra "Release early, release often." or in other words: Run one rule, check the results and commit that to your version control system. Only then should you run the next rule.

## Joomla-specific rector rules
If you want to run all rules (remember: one by one, not all at once!) of this repository, you can copy the `assets/rector.php` to your project as a base configuration. That file contains all rules of this repo and they are sorted by Joomla version, as you can see by the namespaces. That means that for example for an extension supposed to support Joomla 5, you can now run all rules in the `Joomla3`, `Joomla4` and `Joomla5` namespace. The resulting code will run on the latest minor version of that major version. The `Joomla3\MVC` namespace is an exception. Please read more about this [here](mvc.md).

In that default rector.php, the last rule is one to shorten all full class-names to their short version. Please keep in mind that this can also break things, since the rules don't know how to handle classes with the same name. One example would be the `Joomla\CMS\MVC\View\HtmlView` class and the `HtmlView` class of each of your components views. If you simply shorten the name of the core class, your view file all of a sudden reads `class HtmlView extends HtmlView` and that of course is an error. So you have to clear up ambiguities here before running that last rule.
