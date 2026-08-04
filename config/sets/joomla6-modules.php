<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Set
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;

/**
 * Joomla 6 rules that only concern modules.
 *
 * No module rule exists yet. This set is the place to register them once they are written, so
 * that JoomlaSetList::JOOMLA_6_MODULES stays a stable entry point for users.
 */
return static function (RectorConfig $rectorConfig): void {
};
