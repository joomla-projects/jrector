<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Set
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

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
use Rector\Config\RectorConfig;

/**
 * Rules that bring code up to Joomla 5.
 *
 * TableGetInstanceRector is registered without configuration here. To resolve component specific
 * table classes, register it separately with its `component_namespace` option instead.
 */
return static function (RectorConfig $rectorConfig): void {
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

    // Plugins
    // Replaces $this->app / $this->db with getApplication() / getDatabase() in CMSPlugin subclasses.
    $rectorConfig->rule(PluginPropertyToGetterRector::class);
    // Adds SubscriberInterface and getSubscribedEvents() to CMSPlugin subclasses.
    $rectorConfig->rule(PluginSubscriberInterfaceRector::class);
};
