<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Set
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\Rector\Joomla6\Plugin\EventArgumentsToTypedEventRector;
use Rector\Config\RectorConfig;

/**
 * Joomla 6 rules that only concern plugins.
 *
 * Register EventArgumentsToTypedEventRector separately with its `event_argument_map` option if
 * your extension defines its own event classes; the built-in map only covers the core events.
 */
return static function (RectorConfig $rectorConfig): void {
    // Replaces positional and named event argument access with the typed getters of the event class.
    $rectorConfig->rule(EventArgumentsToTypedEventRector::class);
};
