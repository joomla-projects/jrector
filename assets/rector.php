<?php

declare(strict_types=1);

use Joomla\Rector\Joomla3\MVC\Config\JoomlaLegacyPrefixToNamespace;
use Joomla\Rector\Joomla3\MVC\FileRenameCollectorService;
use Joomla\Rector\Joomla3\MVC\FormFieldsRector;
use Joomla\Rector\Joomla3\MVC\FormRulesRector;
use Joomla\Rector\Joomla3\MVC\HelpersToJ4Rector;
use Joomla\Rector\Joomla3\MVC\HtmlHelpersRector;
use Joomla\Rector\Joomla3\MVC\HtmlViewToBaseHtmlViewRector;
use Joomla\Rector\Joomla3\MVC\LegacyMVCToJ4Rector;
use Joomla\Rector\Joomla3\MVC\RenamedClassHandlerService;
use Joomla\Rector\Joomla3\MVC\ViewsTmplMoveRector;
use Joomla\Rector\Joomla3\ViewAssignRefToPropertyRector;
use Joomla\Rector\Joomla4\JimportRector;
use Joomla\Rector\Joomla5\ApplicationInputPropertyRector;
use Joomla\Rector\Joomla5\CurrentUserInterfaceGetUserRector;
use Joomla\Rector\Joomla5\GetDboToGetDatabaseRector;
use Joomla\Rector\Joomla5\HtmlViewGetToModelGetRector;
use Joomla\Rector\Joomla5\LegacyPropertyManagementGetSetRector;
use Joomla\Rector\Joomla5\PluginPropertyToGetterRector;
use Joomla\Rector\Joomla5\PluginSubscriberInterfaceRector;
use Joomla\Rector\Joomla5\TableGetInstanceRector;
use Joomla\Rector\Joomla5\ToolbarHelperToDocumentToolbarRector;
use Joomla\Rector\Joomla5\ViewThisTypehintRector;
use Joomla\Rector\Joomla6\CmsObjectReturnTypeRector;
use Joomla\Rector\Joomla6\HtmlViewExceptionHandlingRector;
use Joomla\Rector\Joomla6\Plugin\EventArgumentsToTypedEventRector;
use Joomla\Rector\Joomla6\SetErrorToExceptionRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

/**
 * Example configuration containing every rule of this repository, grouped by Joomla version and
 * extension type. Each rule has a one-line description of what it does.
 *
 * Do not run all of these at once. Comment out everything, enable one rule, run Rector, review
 * the diff, commit — then move on to the next rule. See docs/index.md.
 *
 * Once your code base is migrated and clean, the ready-made sets in Joomla\Rector\Set\JoomlaSetList
 * are the convenient way to keep it that way in CI. They are for the second pass, not the first.
 */
return static function (RectorConfig $rectorConfig): void {
    // Paths to refactor — adjust to match your project structure.
    $rectorConfig->paths([__DIR__ . '/src']);

    // Provide Joomla core classes for type inference (read-only; never written to).
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

    // Replaces $this->assignRef('key', $value) with $this->key = $value in JView subclasses.
    $rectorConfig->rule(ViewAssignRefToPropertyRector::class);

    /**
     * Refactoring rules for Joomla 4
     */
    $rectorConfig->sets([
        // Replace legacy class names with the namespaced ones
        __DIR__ . '/vendor/joomla-projects/typehints/rector/joomla_4_0.php',
    ]);

    // Removes jimport('joomla.*') calls that are no longer needed in Joomla 4.
    $rectorConfig->rule(JimportRector::class);

    /**
     * Refactoring rules for Joomla 5
     */
    $rectorConfig->sets([
        // Replace classes replaced in Joomla 5.0
        __DIR__ . '/vendor/joomla-projects/typehints/rector/joomla_5_0.php',
    ]);

    // MVC and application
    // Replaces $app->input with $app->getInput() where $app comes from getApplication().
    $rectorConfig->rule(ApplicationInputPropertyRector::class);
    // Replaces Factory::getUser() with $this->getCurrentUser() in CurrentUserInterface classes.
    $rectorConfig->rule(CurrentUserInterfaceGetUserRector::class);
    // Replaces getDbo() calls with getDatabase() in classes using the DatabaseAwareTrait.
    $rectorConfig->rule(GetDboToGetDatabaseRector::class);
    // Replaces $this->get('Items') with $model->getItems() in HtmlView classes and adds @var.
    $rectorConfig->rule(HtmlViewGetToModelGetRector::class);
    // Replaces $this->get()/set() with direct property access in LegacyPropertyManagementTrait users.
    $rectorConfig->rule(LegacyPropertyManagementGetSetRector::class);
    // Replaces Table::getInstance() with direct class instantiation.
    $rectorConfig->rule(TableGetInstanceRector::class);
    // Replaces ToolbarHelper::x() static calls with $toolbar->x() in DocumentAwareInterface classes.
    $rectorConfig->rule(ToolbarHelperToDocumentToolbarRector::class);
    // Adds a /** @var ViewClass $this */ doc comment to view template files in tmpl directories.
    $rectorConfig->rule(ViewThisTypehintRector::class);

    // To resolve component-specific table classes, register TableGetInstanceRector with its
    // component namespace instead of the plain rule() call above:
    // $rectorConfig->ruleWithConfiguration(TableGetInstanceRector::class, [
    //     TableGetInstanceRector::COMPONENT_NAMESPACE => 'Acme\\Component\\Example',
    // ]);

    // Plugins
    // Replaces $this->app / $this->db with getApplication() / getDatabase() in CMSPlugin subclasses.
    $rectorConfig->rule(PluginPropertyToGetterRector::class);
    // Adds SubscriberInterface and getSubscribedEvents() to CMSPlugin subclasses.
    $rectorConfig->rule(PluginSubscriberInterfaceRector::class);

    /**
     * Refactoring rules for Joomla 6
     */
    $rectorConfig->sets([
        // Replace classes replaced in Joomla 6.0
        __DIR__ . '/vendor/joomla-projects/typehints/rector/joomla_6_0.php',
    ]);

    // MVC and application
    // Replaces CMSObject with stdClass in return type hints and @return PHPDoc tags.
    $rectorConfig->rule(CmsObjectReturnTypeRector::class);
    // Adds $model->setUseException(true) after $this->getModel() and removes getErrors() if-blocks.
    $rectorConfig->rule(HtmlViewExceptionHandlingRector::class);
    // Replaces $this->setError('msg') followed by return false with throw new \Exception('msg').
    $rectorConfig->rule(SetErrorToExceptionRector::class);

    // Plugins
    // Replaces positional and named event argument access with the typed getters of the event class.
    $rectorConfig->rule(EventArgumentsToTypedEventRector::class);

    // The built-in event map only covers the Joomla core events. If your extension defines its
    // own event classes, register them instead of the plain rule() call above:
    // $rectorConfig->ruleWithConfiguration(EventArgumentsToTypedEventRector::class, [
    //     EventArgumentsToTypedEventRector::EVENT_ARGUMENT_MAP => [
    //         \Acme\Event\MyCustomEvent::class => ['context', 'item'],
    //     ],
    // ]);

    /**
     * ---------------------------------------------------------------------------------------
     * Structural rules — DISABLED BY DEFAULT
     * ---------------------------------------------------------------------------------------
     *
     * The rules below convert a Joomla 3 component to the namespaced Joomla 4 structure. Unlike
     * every rule above, they do not only rewrite code: they move and create files, and they need
     * a namespace mapping that is specific to your component. Run them once, deliberately, on a
     * clean working tree — never together with the rules above.
     *
     * Read docs/mvc.md before enabling this block.
     */

    // // Disable parallel processing so RenamedClassHandlerService and FileRenameCollectorService
    // // are only instantiated once and their __destruct() writes are not overwritten by other workers.
    // $rectorConfig->disableParallel();
    //
    // // Services required by the Joomla 3 MVC migration rules.
    // $rectorConfig->singleton(RenamedClassHandlerService::class, static function () {
    //     return new RenamedClassHandlerService(__DIR__);
    // });
    //
    // $rectorConfig->singleton(FileRenameCollectorService::class);
    //
    // // Namespace mapping — adjust the prefix and target namespace to your component.
    // // Add one entry per distinct casing of the legacy prefix (Joomla 3 is case-insensitive).
    // $joomlaNamespaceMaps = [
    //     new JoomlaLegacyPrefixToNamespace('Helloworld', 'Acme\HelloWorld', []),
    // ];
    //
    // // Converts legacy Joomla 3 Helper class names into Joomla 4 namespaced ones.
    // $rectorConfig->ruleWithConfiguration(HelpersToJ4Rector::class, $joomlaNamespaceMaps);
    // // Converts legacy Joomla 3 HTML Helper class names into Joomla 4 namespaced ones.
    // $rectorConfig->ruleWithConfiguration(HtmlHelpersRector::class, $joomlaNamespaceMaps);
    // // Converts legacy Joomla 3 JFormField class names into Joomla 4 namespaced ones.
    // $rectorConfig->ruleWithConfiguration(FormFieldsRector::class, $joomlaNamespaceMaps);
    // // Converts legacy Joomla 3 form rule class names into Joomla 4 namespaced ones.
    // $rectorConfig->ruleWithConfiguration(FormRulesRector::class, $joomlaNamespaceMaps);
    // // Converts models, views, controllers and tables into their namespaced variants.
    // $rectorConfig->ruleWithConfiguration(LegacyMVCToJ4Rector::class, $joomlaNamespaceMaps);
    // // Registers view layouts so they are moved from views/<view>/tmpl/ to tmpl/<view>/.
    // $rectorConfig->rule(ViewsTmplMoveRector::class);
    // // Imports Joomla\CMS\MVC\View\HtmlView as BaseHtmlView to avoid a name collision later.
    // $rectorConfig->rule(HtmlViewToBaseHtmlViewRector::class);

    /**
     * ---------------------------------------------------------------------------------------
     */

    // Shorten FQCNs to short names and insert use statements.
    // CAUTION: classes with the same short name in your code and in the Joomla core
    // (e.g. HtmlView) will cause fatal conflicts — resolve all ambiguities first.
    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);

    /**
     * End refactoring rules
     */
};
