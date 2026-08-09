# Joomla Rector Rules

Custom [Rector](https://getrector.com/) rules for upgrading Joomla extensions from old Joomla versions up to the Joomla 7.

This page is an index. The full documentation for each rule — what it changes, what it deliberately does not change, and how to configure it — lives in the per-version pages linked below.

## [Joomla 3](rules/joomla3.md)

- [HtmlViewToBaseHtmlViewRector](rules/joomla3.md#htmlviewtobasehtmlviewrector) — Imports `Joomla\CMS\MVC\View\HtmlView` under the alias `BaseHtmlView` in classes that extend it directly.
- [ViewAssignRefToPropertyRector](rules/joomla3.md#viewassignreftopropertyrector) — Replaces `$this->assignRef('key', $value)` with `$this->key = $value` in `JView` subclasses.

## [Joomla 3 to 4 component structure](mvc.md)

These rules convert a Joomla 3 component to the namespaced Joomla 4 structure. They need a namespace mapping specific to your component and they move files on disk, so they are documented together in [the MVC guide](mvc.md) rather than individually, and they are not part of any rule set.

- [FormFieldsRector](mvc.md) — Converts legacy `JFormField` class names into Joomla 4 namespaced ones.
- [FormRulesRector](mvc.md) — Converts legacy form rule class names into Joomla 4 namespaced ones.
- [HelpersToJ4Rector](mvc.md) — Converts legacy helper class names into Joomla 4 namespaced ones.
- [HtmlHelpersRector](mvc.md) — Converts legacy HTML helper class names into Joomla 4 namespaced ones.
- [LegacyMVCToJ4Rector](mvc.md) — Converts models, views, controllers and tables into their namespaced variants and updates all references across the code base.
- [ViewsTmplMoveRector](mvc.md) — Registers view layouts so they are moved from `views/<view>/tmpl/` to `tmpl/<view>/`.

## [Joomla 4](rules/joomla4.md)

- [JimportRector](rules/joomla4.md#jimportrector) — Removes `jimport('joomla.*')` calls that are no longer needed in Joomla 4.

## [Joomla 5](rules/joomla5.md)

- [ApplicationInputPropertyRector](rules/joomla5.md#applicationinputpropertyrector) — Replaces `$app->input` with `$app->getInput()` where `$app` comes from `getApplication()`.
- [CurrentUserInterfaceGetUserRector](rules/joomla5.md#currentuserinterfacegetuserrector) — Replaces `Factory::getUser()` with `$this->getCurrentUser()` in classes implementing `CurrentUserInterface`.
- [GetDboToGetDatabaseRector](rules/joomla5.md#getdbotogetdatabaserector) — Replaces `getDbo()` calls with `getDatabase()` in classes using the `DatabaseAwareTrait`.
- [HtmlViewGetToModelGetRector](rules/joomla5.md#htmlviewgettomodelgetrector) — Replaces `$this->get('Items')` with `$model->getItems()` in `HtmlView` classes and adds a `@var` typehint.
- [LegacyPropertyManagementGetSetRector](rules/joomla5.md#legacypropertymanagementgetsetrector) — Replaces `$this->get()` / `set()` with direct property access in classes using `LegacyPropertyManagementTrait`.
- [PluginPropertyToGetterRector](rules/joomla5.md#pluginpropertytogetterrector) — Replaces `$this->app` / `$this->db` with `getApplication()` / `getDatabase()` in `CMSPlugin` subclasses.
- [PluginSubscriberInterfaceRector](rules/joomla5.md#pluginsubscriberinterfacerector) — Adds `SubscriberInterface` and `getSubscribedEvents()` to `CMSPlugin` subclasses.
- [TableGetInstanceRector](rules/joomla5.md#tablegetinstancerector) — Replaces `Table::getInstance()` with direct class instantiation.
- [ToolbarHelperToDocumentToolbarRector](rules/joomla5.md#toolbarhelpertodocumenttoolbarrector) — Replaces `ToolbarHelper::x()` static calls with `$toolbar->x()` in classes implementing `DocumentAwareInterface`.
- [ViewThisTypehintRector](rules/joomla5.md#viewthistypehintrector) — Adds a `/** @var ViewClass $this */` doc comment to view template files found in `tmpl` directories.

## [Joomla 6](rules/joomla6.md)

- [AllowLegacyListenersRector](rules/joomla6.md#allowlegacylistenersrector) — Removes the deprecated `$allowLegacyListeners` property from plugins implementing `SubscriberInterface`.
- [CmsObjectReturnTypeRector](rules/joomla6.md#cmsobjectreturntyperector) — Replaces `CMSObject` with `stdClass` in return type hints and `@return` PHPDoc tags.
- [CountModulesRector](rules/joomla6.md#countmodulesrector) — Splits `countModules()` condition strings such as `'a and b'` into individual calls.
- [DispatcherGetLayoutDataRector](rules/joomla6.md#dispatchergetlayoutdatarector) — Converts a hand written module `dispatch()` method into `getLayoutData()`.
- [DocumentAssetsToWebAssetManagerRector](rules/joomla6.md#documentassetstowebassetmanagerrector) — Replaces direct document `addStyleSheet()` / `addScript()` calls with the WebAssetManager.
- [EventArgumentsToTypedEventRector](rules/joomla6.md#eventargumentstotypedeventrector) — Replaces positional and named event argument access with the typed getters of the concrete event class.
- [FactoryGetDocumentRector](rules/joomla6.md#factorygetdocumentrector) — Replaces `Factory::getDocument()` with the getter that fits the context.
- [HandlerReturnToEventResultRector](rules/joomla6.md#handlerreturntoeventresultrector) — Writes a plugin handler return value into the event result instead of returning it.
- [HtmlViewExceptionHandlingRector](rules/joomla6.md#htmlviewexceptionhandlingrector) — Adds `$model->setUseException(true)` after `$this->getModel()` and removes legacy `getErrors()` if-blocks.
- [JpathPlatformToJexecRector](rules/joomla6.md#jpathplatformtojexecrector) — Switches the direct access guard from `JPATH_PLATFORM` to `_JEXEC`.
- [LegacyHandlerSignatureRector](rules/joomla6.md#legacyhandlersignaturerector) — Converts a legacy plugin handler signature to the typed event object signature.
- [ModuleHelperStaticToHelperFactoryRector](rules/joomla6.md#modulehelperstatictohelperfactoryrector) — Replaces static module helper calls with the `HelperFactory` and turns helper methods into instance methods.
- [ModuleTmplTypehintRector](rules/joomla6.md#moduletmpltypehintrector) — Adds `@var` annotations for the standard layout variables to module template files.
- [SetErrorToExceptionRector](rules/joomla6.md#seterrortoexceptionrector) — Replaces `$this->setError('msg')` followed by `return false` with `throw new \Exception('msg')`.
- [TemplateThisTypehintRector](rules/joomla6.md#templatethistypehintrector) — Adds the `@var $this` annotation to `index.php`, `component.php`, `offline.php` and `error.php`.

## Rule sets

Instead of registering the rules one by one, `Joomla\Rector\Set\JoomlaSetList` offers ready-made sets:

```php
use Joomla\Rector\Set\JoomlaSetList;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__ . '/src']);
    $rectorConfig->sets([JoomlaSetList::JOOMLA_6]);
};
```

| Constant | Contents |
|----------|----------|
| `JoomlaSetList::JOOMLA_3` | Joomla 3 rules, without the structural `Joomla3\MVC` rules |
| `JoomlaSetList::JOOMLA_4` | Joomla 4 rules |
| `JoomlaSetList::JOOMLA_5` | Joomla 5 rules |
| `JoomlaSetList::JOOMLA_6` | Joomla 6 rules, including the three sets below |
| `JoomlaSetList::JOOMLA_6_PLUGINS` | Joomla 6 rules that only concern plugins |
| `JoomlaSetList::JOOMLA_6_MODULES` | Joomla 6 rules that only concern modules |
| `JoomlaSetList::JOOMLA_6_TEMPLATES` | Joomla 6 rules that only concern templates |
| `JoomlaSetList::UP_TO_JOOMLA_6` | Joomla 3 to Joomla 6 combined |

**Sets are for the second pass, not the first.** A set applies many rules in one run, which is exactly what the [migration advice](index.md#how-to-properly-refactor-your-code) tells you not to do while migrating: you cannot review a diff that touches everything at once. Migrate rule by rule, then use a set in CI to keep the code base from drifting back.

Two kinds of rules are deliberately missing from every set:

- The `Joomla3\MVC` rules, which convert a component to the namespaced Joomla 4 structure. They need a namespace mapping specific to your component and they move files on disk — see [the MVC guide](mvc.md).
- Any other rule that creates or moves files. Restructuring an extension has to be a deliberate, separately reviewed step.
