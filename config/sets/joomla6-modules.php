<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Set
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\Rector\Joomla6\Module\DispatcherGetLayoutDataRector;
use Joomla\Rector\Joomla6\Module\ModuleHelperStaticToHelperFactoryRector;
use Joomla\Rector\Joomla6\Module\ModuleTmplTypehintRector;
use Rector\Config\RectorConfig;

/**
 * Joomla 6 rules that only concern modules.
 *
 * These rules assume a module that already uses the namespaced structure with a dispatcher.
 * Converting a legacy `mod_<name>.php` module to that structure is a separate, file-moving
 * step — run `php tools/analyse-legacy-modules.php <path>` to see which modules still need it.
 */
return static function (RectorConfig $rectorConfig): void {
    // Converts a hand written module dispatch() method into getLayoutData().
    $rectorConfig->rule(DispatcherGetLayoutDataRector::class);
    // Replaces static module helper calls with the HelperFactory and de-statics the helper.
    $rectorConfig->rule(ModuleHelperStaticToHelperFactoryRector::class);
    // Adds @var annotations for the standard layout variables to module template files.
    $rectorConfig->rule(ModuleTmplTypehintRector::class);
};
