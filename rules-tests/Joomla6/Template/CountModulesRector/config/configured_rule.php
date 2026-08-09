<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla6
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\Rector\Joomla6\Template\CountModulesRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    // Minimal HtmlDocument stub so the receiver type can be resolved without a real Joomla.
    $rectorConfig->autoloadPaths([__DIR__ . "/../JoomlaStub"]);

    $rectorConfig->rule(CountModulesRector::class);
};
