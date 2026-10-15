<?php

declare(strict_types=1);

use Joomla\Rector\Extension\ExtensionTemplateFactory;
use Joomla\Rector\FileSystem\AddedFileCollectorService;
use Joomla\Rector\Joomla3\MVC\ComponentRouterNamespaceRector;
use Joomla\Rector\Joomla3\MVC\ComponentServiceProviderRector;
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
use Joomla\Rector\Joomla5\TableGetInstanceRector;
use Joomla\Rector\Joomla6\JpathPlatformToJexecRector;
use Joomla\Rector\Joomla6\Module\LegacyModuleToJ6Rector;
use Joomla\Rector\Joomla6\Module\ModuleTmplTypehintRector;
use Joomla\Rector\Joomla6\Plugin\AllowLegacyListenersRector;
use Joomla\Rector\Joomla6\Plugin\EventArgumentsToTypedEventRector;
use Joomla\Rector\Joomla6\Plugin\PluginServiceProviderRector;
use Joomla\Rector\Joomla6\Template\CountModulesRector;
use Joomla\Rector\Joomla6\Template\DocumentAssetsToWebAssetManagerRector;
use Joomla\Rector\Joomla7\GetInstanceToContainerRector;
use Joomla\Rector\Joomla7\ResultIsFalseablePropertyRector;
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
    $rectorConfig->sets([
        __DIR__ . '/vendor/joomla-projects/jrector/config/sets/joomla3.php',
    ]);

    /**
     * Refactoring rules for Joomla 4
     */
    $rectorConfig->sets([
        __DIR__ . '/vendor/joomla-projects/jrector/config/sets/joomla4.php',
    ]);

    /**
     * ---------------------------------------------------------------------------------------
     * Convert component from Joomla 3 to Joomla 4 — DISABLED BY DEFAULT
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
    // // Namespaces the component router and moves it to src/Service/Router.php.
    // $rectorConfig->ruleWithConfiguration(ComponentRouterNamespaceRector::class, $joomlaNamespaceMaps);
    //
    // // Creates services/provider.php and src/Extension/<Name>Component.php. Needs the
    // // AddedFileCollectorService, which actually writes the generated files.
    // $rectorConfig->singleton(AddedFileCollectorService::class);
    // $rectorConfig->singleton(ExtensionTemplateFactory::class);
    // $rectorConfig->ruleWithConfiguration(ComponentServiceProviderRector::class, $joomlaNamespaceMaps);

    /**
     * ---------------------------------------------------------------------------------------
     */

    /**
     * Refactoring rules for Joomla 5
     */
    $rectorConfig->sets([
        __DIR__ . '/vendor/joomla-projects/jrector/config/sets/joomla5.php',
    ]);

    // To resolve component-specific table classes, register TableGetInstanceRector with its
    // component namespace instead of the plain rule() call in the above set:
    // $rectorConfig->ruleWithConfiguration(TableGetInstanceRector::class, [
    //     TableGetInstanceRector::COMPONENT_NAMESPACE => 'Acme\\Component\\Example',
    // ]);

    /**
     * Refactoring rules for Joomla 6
     */
    $rectorConfig->sets([
        __DIR__ . '/vendor/joomla-projects/jrector/config/sets/joomla6.php',
    ]);

    // Also flag other JPATH_PLATFORM usages (path expressions) with a TODO comment:
    // $rectorConfig->ruleWithConfiguration(JpathPlatformToJexecRector::class, [
    //     JpathPlatformToJexecRector::MARK_OTHER_USAGES => true,
    // ]);

    // Plugins
    // // The built-in event map only covers the Joomla core events. If your extension defines its
    // // own event classes, register them instead of the plain rule() calls above:
    // $rectorConfig->ruleWithConfiguration(EventArgumentsToTypedEventRector::class, [
    //     EventArgumentsToTypedEventRector::EVENT_ARGUMENT_MAP => [
    //         \Acme\Event\MyCustomEvent::class => ['context', 'item'],
    //     ],
    // ]);

    // // AllowLegacyListenersRector removes the deprecated property by default. To keep it and only
    // // force it to false instead:
    // $rectorConfig->ruleWithConfiguration(AllowLegacyListenersRector::class, [
    //     AllowLegacyListenersRector::MODE => AllowLegacyListenersRector::MODE_SET_FALSE,
    // ]);

    // Templates
    // The generated calls keep the old default counting. Pass true as the second argument
    // instead, i.e. count only modules that actually render content:
    // $rectorConfig->ruleWithConfiguration(CountModulesRector::class, [
    //     CountModulesRector::WITH_CONTENT_ONLY => true,
    // ]);

    // Prefix every derived web asset name, e.g. with your vendor:
    // $rectorConfig->ruleWithConfiguration(DocumentAssetsToWebAssetManagerRector::class, [
    //    DocumentAssetsToWebAssetManagerRector::ASSET_NAME_PREFIX => 'acme.',
    // ]);

    /**
     * Refactoring rules for Joomla 7
     */
    $rectorConfig->sets([
        __DIR__ . '/vendor/joomla-projects/jrector/config/sets/joomla7.php',
    ]);

    // The set above contains these rules. To run one of them alone:
    // // Replaces the static getInstance() accessors removed in Joomla 7 (Pathway, Session,
    // // Cache, CacheController) with the application or the container.
    // $rectorConfig->rule(GetInstanceToContainerRector::class);
    // // Removes the $resultIsFalseable property that the ResultType*Aware traits no longer have.
    // $rectorConfig->rule(ResultIsFalseablePropertyRector::class);


    /**
     * ---------------------------------------------------------------------------------------
     * Structural plugin and module rules — DISABLED BY DEFAULT
     * ---------------------------------------------------------------------------------------
     *
     * These move and create files, exactly like the Joomla3\MVC block further down. They need
     * a vendor namespace, which is never guessed, and the services that write the results:
     * FileRenameCollectorService produces rename.php, AddedFileCollectorService creates the
     * generated services/provider.php files.
     *
     * Run them once, deliberately, on a clean working tree, then execute the generated
     * rename.php. Read docs/rules.md before enabling this block.
     */

    // $rectorConfig->disableParallel();
    // $rectorConfig->singleton(FileRenameCollectorService::class);
    // $rectorConfig->singleton(AddedFileCollectorService::class);
    // $rectorConfig->singleton(ExtensionTemplateFactory::class);
    //
    // // Converts a legacy module to the namespaced structure with a service provider.
    // $rectorConfig->ruleWithConfiguration(LegacyModuleToJ6Rector::class, [
    //     LegacyModuleToJ6Rector::VENDOR_NAMESPACE => 'Acme',
    // ]);
    //
    // // Converts a legacy single file plugin to the namespaced structure with a provider.
    // $rectorConfig->ruleWithConfiguration(PluginServiceProviderRector::class, [
    //     PluginServiceProviderRector::VENDOR_NAMESPACE => 'Acme',
    // ]);
    //
    // // Extra layout variables your module passes to its templates:
    // $rectorConfig->ruleWithConfiguration(ModuleTmplTypehintRector::class, [
    //     ModuleTmplTypehintRector::EXTRA_VARIABLES => ['items' => '\\stdClass[]'],
    // ]);

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
