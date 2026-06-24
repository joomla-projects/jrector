<?php

declare(strict_types=1);

use Joomla\Rector\Joomla3\MVC\Config\JoomlaLegacyPrefixToNamespace;
use Joomla\Rector\Joomla3\MVC\FileRenameCollectorService;
use Joomla\Rector\Joomla3\MVC\FormFieldsRector;
use Joomla\Rector\Joomla3\MVC\FormRulesRector;
use Joomla\Rector\Joomla3\MVC\HelpersToJ4Rector;
use Joomla\Rector\Joomla3\MVC\HtmlHelpersRector;
use Joomla\Rector\Joomla3\MVC\LegacyMVCToJ4Rector;
use Joomla\Rector\Joomla3\MVC\RenamedClassHandlerService;
use Joomla\Rector\Joomla3\ViewAssignRefToPropertyRector;
use Joomla\Rector\Joomla4\JimportRector;
use Joomla\Rector\Joomla5\ApplicationInputPropertyRector;
use Joomla\Rector\Joomla5\CurrentUserInterfaceGetUserRector;
use Joomla\Rector\Joomla5\GetDboToGetDatabaseRector;
use Joomla\Rector\Joomla5\HtmlViewGetToModelGetRector;
use Joomla\Rector\Joomla5\PluginPropertyToGetterRector;
use Joomla\Rector\Joomla5\PluginSubscriberInterfaceRector;
use Joomla\Rector\Joomla5\TableGetInstanceRector;
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

        // Use early returns in if-blocks (code quality)
        SetList::EARLY_RETURN,
    ]);

    /**
     * Refactoring rules to optimize code to Joomla 3.10
     */
    $rectorConfig->rule(ViewAssignRefToPropertyRector::class);

    // Convert to J4 classes directly
    $rectorConfig->sets([
        // Replace legacy class names with the namespaced ones
        __DIR__ . '/vendor/joomla-projects/typehints/rector/joomla_4_0.php',
    ]);

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

    /**
     * Refactoring rules for Joomla 4
     */
    $rectorConfig->rule(JimportRector::class);

    /**
     * Refactoring rules for Joomla 5
     */
    $rectorConfig->sets([
        // Replace classes replaced in Joomla 6.0
        __DIR__ . '/vendor/joomla-projects/typehints/rector/joomla_5_0.php',
    ]);
    $rectorConfig->rule(CurrentUserInterfaceGetUserRector::class);
    $rectorConfig->rule(GetDboToGetDatabaseRector::class);
    $rectorConfig->rule(HtmlViewGetToModelGetRector::class);
    $rectorConfig->rule(ViewThisTypehintRector::class);
    $rectorConfig->rule(ApplicationInputPropertyRector::class);
    $rectorConfig->rule(PluginPropertyToGetterRector::class);
    $rectorConfig->rule(PluginSubscriberInterfaceRector::class);
    $rectorConfig->rule(TableGetInstanceRector::class);

    /**
     * Refactoring rules for Joomla 6
     */
    $rectorConfig->sets([
        // Replace classes replaced in Joomla 6.0
        __DIR__ . '/vendor/joomla-projects/typehints/rector/joomla_6_0.php',
    ]);
    $rectorConfig->rule(HtmlViewExceptionHandlingRector::class);


    // Add use statements at the top of files for everything except short classes like \stdClass
    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);

    /**
     * End refactoring rules
     */
};
