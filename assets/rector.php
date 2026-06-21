<?php

declare(strict_types=1);

use Joomla\Rector\Joomla3\ViewAssignRefToPropertyRector;
use Joomla\Rector\Joomla4\JimportRector;
use Joomla\Rector\Joomla5\CurrentUserInterfaceGetUserRector;
use Joomla\Rector\Joomla5\GetDboToGetDatabaseRector;
use Joomla\Rector\Joomla5\HtmlViewGetToModelGetRector;
use Joomla\Rector\Joomla5\ViewThisTypehintRector;
use Joomla\Rector\Joomla6\HtmlViewExceptionHandlingRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    // Define the path (or paths) to refactor
    $rectorConfig->paths([__DIR__ . '/src']);

    // Add additional path to load files for context, in this case a copy of Joomla to understand Joomla core classes
    $rectorConfig->autoloadPaths([
        __DIR__ . '/joomla',
    ]);

    /**
     * Start refactoring rules
     */

    // Basic refactorings
    $rectorConfig->sets([
        // Auto-refactor code to at least PHP 8.1 (minimum Joomla 6 version)
        LevelSetList::UP_TO_PHP_81,

        // Replace legacy class names with the namespaced ones
        __DIR__ . '/vendor/joomla-projects/typehints/rector/joomla_4_0.php',

        // Use early returns in if-blocks (code quality)
        SetList::EARLY_RETURN,
    ]);

    /**
     * Refactoring rules to optimize code to Joomla 3.10
     */
    $rectorConfig->rule(ViewAssignRefToPropertyRector::class);

    // MVC refactoring rules
    $rectorConfig->singleton(RenamedClassHandlerService::class, static function () {
        return new RenamedClassHandlerService(__DIR__);
    });

    // Configure the namespace mappings
    $joomlaNamespaceMaps = [
        new JoomlaLegacyPrefixToNamespace('Helloworld', 'Acme\HelloWorld', []),
    ];

    $rectorConfig->ruleWithConfiguration(JoomlaHelpersToJ4Rector::class, $joomlaNamespaceMaps);
    $rectorConfig->ruleWithConfiguration(JoomlaHtmlHelpersRector::class, $joomlaNamespaceMaps);
    $rectorConfig->ruleWithConfiguration(JoomlaFormFieldsRector::class, $joomlaNamespaceMaps);
    $rectorConfig->ruleWithConfiguration(JoomlaFormRulesRector::class, $joomlaNamespaceMaps);
    $rectorConfig->ruleWithConfiguration(JoomlaLegacyMVCToJ4Rector::class, $joomlaNamespaceMaps);

    /**
     * Refactoring rules for Joomla 4
     */
    $rectorConfig->rule(JimportRector::class);

    /**
     * Refactoring rules for Joomla 5
     */
    $rectorConfig->rule(CurrentUserInterfaceGetUserRector::class);
    $rectorConfig->rule(GetDboToGetDatabaseRector::class);
    $rectorConfig->rule(HtmlViewGetToModelGetRector::class);
    $rectorConfig->rule(ViewThisTypehintRector::class);

    /**
     * Refactoring rules for Joomla 6
     */
    $rectorConfig->rule(HtmlViewExceptionHandlingRector::class);


    // Add use statements at the top of files for everything except short classes like \stdClass
    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);

    /**
     * End refactoring rules
     */
};
