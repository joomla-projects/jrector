<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Set
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\Rector\Set\JoomlaSetList;
use Rector\Config\RectorConfig;

/**
 * Everything from Joomla 3 up to Joomla 6.
 *
 * Structural rules that create or move files are not part of this set. Converting a component to
 * the namespaced Joomla 4 structure changes the layout of an extension and has to be a deliberate,
 * separately reviewed step — see docs/mvc.md.
 *
 * This is a lot of change at once. Use it to keep an already migrated code base clean, not to
 * perform the first migration.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        JoomlaSetList::JOOMLA_3,
        JoomlaSetList::JOOMLA_4,
        JoomlaSetList::JOOMLA_5,
        JoomlaSetList::JOOMLA_6,
    ]);
};
