<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Set
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\Rector\Joomla3\ViewAssignRefToPropertyRector;
use Rector\Config\RectorConfig;

/**
 * Rules that clean up Joomla 3 code so it runs on Joomla 3.10.
 *
 * The rules in the Joomla3\MVC namespace are NOT part of this set. They convert a component to
 * the namespaced Joomla 4 structure, which requires a per-project namespace mapping and moves
 * files on disk. Registering them from a set would silently restructure an extension. Configure
 * them by hand as described in docs/mvc.md.
 */
return static function (RectorConfig $rectorConfig): void {
    // Replaces $this->assignRef('key', $value) with $this->key = $value in JView subclasses.
    $rectorConfig->rule(ViewAssignRefToPropertyRector::class);
};
