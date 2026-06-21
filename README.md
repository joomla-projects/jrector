# Joomla Rector rules to upgrade extension code

Rector rules to easily upgrade Joomla extension code to the latest Joomla versions

This code is based on the excellent work of [Nicholas K. Dionysopoulos](https://www.akeeba.com/) [Component Upgrader](https://github.com/nikosdion/joomla_com_upgrader). Thank you very much both for the actual code and also the inspiration to extend this further.

## What is this all about?

This repository provides Rector rules to automatically refactor your legacy Joomla code to newer Joomla versions. The rules are categorized by the Joomla version, where the newer code starts working. So when you want to support Joomla 5 and up, you can use all rules in Joomla3, Joomla4 and Joomla5. The set of available rules does not cover all changes in the different Joomla versions, but should take over the most tedious work for you and prepare your code to be properly analysed in phpstan later on.

The `Joomla3/MVC` rules also contain the code to convert your extension from Joomla 3 structure to Joomla 4+ structure. It is important to note, that it does not do everything. It will definitely _not_ result in a _fully working_ Joomla 4 component. The goal of this tool is to automate the boring, repeated and soul–crushing work. It sets you off to a great start into refactoring a legacy Joomla 3 component into a new Joomla 4+ MVC modern component.

## Getting started

Please read the [documentation](docs/index.md)! The easiest start is by installing Rector and then using the default [rector.php](assets/rector.php).

1. Make sure you have your code in a git repository in a subfolder. 
2. Run `composer require --dev rector/rector joomla-projects/jrector joomla-projects/typehints` in your repositories root folder.
3. Copy the `rector.php` from the assets folder into the root of your repository. (Either download the file from the repo [here](assets/rector.php) or copy it from `vendor/joomla-projects/jrector/assets/rector.php`)
4. Add a `/joomla` folder with a copy of the Joomla version you want to support to the root of your repository. Add the folder to your `.gitignore` file to simplify further handling. The folder is necessary to provide necessary context for Rectors code analysis.
4. Modify the `rector.php` as necessary and comment all the rules that you do _not_ wish to run this time. You should modify the code one rule at a time, not everything at once!
5. Run `vendor/bin/rector` in the root of your git repo. This __will__ modify the files in your repository. If you want to review the changes before applying them, add the parameter `--dry-run` to the call.
6. Review the changes and commit them to your repository when correct.
7. Go back to step 4 and add additional rules until you are satisfied with the result.

You might have to run Rector multiple times to catch all changes, for example when one rule did changes which another rule would further refine.

## Requirements

* Rector 2
* PHP 8.1 or later
* Composer 2.x
