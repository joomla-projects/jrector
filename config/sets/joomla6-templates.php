<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Set
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\Rector\Joomla6\Template\CountModulesRector;
use Joomla\Rector\Joomla6\Template\DocumentAssetsToWebAssetManagerRector;
use Joomla\Rector\Joomla6\Template\FactoryGetDocumentRector;
use Joomla\Rector\Joomla6\Template\TemplateThisTypehintRector;
use Rector\Config\RectorConfig;

/**
 * Joomla 6 rules that concern templates.
 *
 * FactoryGetDocumentRector lives in this namespace because it was written for the template
 * migration, but it applies to any class that reaches for Factory::getDocument() — views,
 * plugins and services included.
 */
return static function (RectorConfig $rectorConfig): void {
    // Splits countModules() condition strings into individual calls.
    $rectorConfig->rule(CountModulesRector::class);
    // Replaces direct document asset calls with the WebAssetManager.
    $rectorConfig->rule(DocumentAssetsToWebAssetManagerRector::class);
    // Replaces Factory::getDocument() with the getter that fits the context.
    $rectorConfig->rule(FactoryGetDocumentRector::class);
    // Adds the @var $this annotation to Joomla template files.
    $rectorConfig->rule(TemplateThisTypehintRector::class);
};
