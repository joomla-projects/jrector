# Documentation for Joomla Rector rules

This is the documentation for using the Rector rules to update the code of Joomla extensions. The rules take over the most tedious work when removing deprecated code and code constructs.

- [Getting started](#getting-started)
- [List of rules](rules.md)
- [How to convert the component MVC structure from Joomla 3 to Joomla 4](mvc.md)

## What is Rector?

Rector is a powerful tool to convert PHP code based on predefined rules. If you are interested in an in-depth read on this, please look [here](https://getrector.com/documentation). Rector is not just a fancy search-and-replace tool, but goes a lot deeper. It reads your code with the static code analyser `phpstan` and tries to understand your code regardless of how it is formatted or structured. It will for example understand both `$this->test();` and `$this->test ();`, but also match on classes that do not just directly, but also indirectly inherit from another class. It can then convert existing code in quite complex ways.

## Getting started

First of all you have to install Rector via Composer by calling:

```
composer require --dev rector/rector joomla-projects/jrector joomla-projects/typehints
```

After this, you can call it via `vendor/bin/rector` and it will start converting your code based on your configuration. Do not run this without a configuration first. If you only want to preview what would change, run it with `vendor/bin/rector --dry-run`.

## Configuring Rector

To actually do any work, you have to configure Rector with a `rector.php` file in the root of your project or repository. You can find a default `rector.php` in the `assets` folder, which includes all rules in this repository. If you want to write your own, follow along. The simplest `rector.php` looks like this:

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
};
```

As you can imagine this is not doing anything yet. Let's add the path to our source code:

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    // Define the path (or paths) to refactor
    $rectorConfig->paths([__DIR__ . '/src']);
};
```

Note that you are passing an array of paths, so if your repository does not have all the code in a `/src` folder, you can list multiple folders individually. Everything in the given folders will be processed.

Rector automatically reads all code from the `vendor` folder for context, but in the case of Joomla you normally do not have the CMS in the `vendor` folder. Add a folder with a Joomla installation so Rector can understand all Joomla core classes. This code will only be read, not written to:

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    // Define the path (or paths) to refactor
    $rectorConfig->paths([__DIR__ . '/src']);

    // Add additional path for context — a copy of Joomla to understand its core classes
    $rectorConfig->autoloadPaths([
        __DIR__ . '/joomla',
    ]);
};
```

Next, add the rules that do the actual processing. Three ways to register rules are relevant:

```php
<?php

declare(strict_types=1);

use Joomla\Rector\Joomla3\MVC\Config\JoomlaLegacyPrefixToNamespace;
use Joomla\Rector\Joomla3\MVC\LegacyMVCToJ4Rector;
use Joomla\Rector\Joomla4\JimportRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    // Define the path (or paths) to refactor
    $rectorConfig->paths([__DIR__ . '/src']);

    // Add additional path for context — a copy of Joomla to understand its core classes
    $rectorConfig->autoloadPaths([
        __DIR__ . '/joomla',
    ]);

    // Rule without configuration
    $rectorConfig->rule(JimportRector::class);

    // Rule with configuration
    $joomlaNamespaceMaps = [
        new JoomlaLegacyPrefixToNamespace('Helloworld', 'Acme\HelloWorld', []),
    ];
    $rectorConfig->ruleWithConfiguration(LegacyMVCToJ4Rector::class, $joomlaNamespaceMaps);

    // Ready-made set of rules
    $rectorConfig->sets([
        // Auto-refactor code to at least PHP 8.1 (minimum for Joomla 6)
        LevelSetList::UP_TO_PHP_81,
    ]);
};
```

- `$rectorConfig->rule(ClassName::class)` — adds a single rule without configuration.
- `$rectorConfig->ruleWithConfiguration(ClassName::class, $config)` — adds a rule with additional configuration, for example a namespace mapping object.
- `$rectorConfig->sets([...])` — adds a ready-made set of rules.

## How to properly refactor your code

Your Rector configuration is ready. Running all rules at once and hoping for the best is a recipe for disaster. Instead, make changes in small, reviewable chunks.

The easiest approach is to run only one rule at a time. Comment out everything you are not ready to process yet and run `vendor/bin/rector` with just that one rule. You may get a bunch of changes, or none at all. Go through each change and verify it is correct. Commit to version control when you are satisfied. Then uncomment the next rule and repeat. Previously applied rules usually do not need to be removed; they may even find additional regressions introduced by later rules.

The important point: Rector is not a fire-and-forget solution. Even with good rules, mistakes can happen. You must review every change the tool makes. That becomes very difficult when 30 rules each make major changes to your codebase at the same time. Follow the principle "run one rule, review the results, commit" — only then move on to the next rule.

## Joomla-specific Rector rules

To run all rules in this repository (remember: one by one, not all at once!), copy `assets/rector.php` to your project as a base configuration. That file contains all rules sorted by Joomla version, which is visible in the namespaces. For an extension that targets Joomla 5, run all rules in the `Joomla3`, `Joomla4`, and `Joomla5` namespaces. The resulting code will run on the latest minor version of that major version.

The `Joomla3\MVC` namespace is an exception — please read more about this [here](mvc.md).

The last rule in the default `rector.php` shortens all fully-qualified class names to their short form. Keep in mind that this can break things when two classes share the same short name. One example is `Joomla\CMS\MVC\View\HtmlView` and the `HtmlView` class of each of your component views. If you blindly shorten the core class name, your view file suddenly reads `class HtmlView extends HtmlView`, which is a fatal error. Resolve all such ambiguities before running that last rule.
