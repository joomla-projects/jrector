<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Set
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\Rector\Joomla6\Plugin\AllowLegacyListenersRector;
use Joomla\Rector\Joomla6\Plugin\EventArgumentsToTypedEventRector;
use Joomla\Rector\Joomla6\Plugin\HandlerReturnToEventResultRector;
use Joomla\Rector\Joomla6\Plugin\LegacyHandlerSignatureRector;
use Rector\Config\RectorConfig;

/**
 * Joomla 6 rules that only concern plugins.
 *
 * The order below is the order these rules should run in:
 *
 *   1. PluginSubscriberInterfaceRector (Joomla 5) — declares the handlers explicitly.
 *   2. LegacyHandlerSignatureRector    — gives the handlers an event parameter.
 *   3. EventArgumentsToTypedEventRector— rewrites argument access inside the handlers.
 *   4. HandlerReturnToEventResultRector— moves return values into the event result.
 *   5. AllowLegacyListenersRector      — drops the then dead legacy listener property.
 *
 * Steps 2 and 3 depend on each other: the argument rule only touches handlers that already have
 * a typed event parameter, which is exactly what the signature rule produces.
 *
 * Register EventArgumentsToTypedEventRector, HandlerReturnToEventResultRector or
 * LegacyHandlerSignatureRector separately with their `event_argument_map` option if your
 * extension defines its own event classes; the built-in map only covers the core events.
 */
return static function (RectorConfig $rectorConfig): void {
    // Converts a legacy handler signature to the typed event object signature.
    $rectorConfig->rule(LegacyHandlerSignatureRector::class);
    // Replaces positional and named event argument access with the typed getters of the event class.
    $rectorConfig->rule(EventArgumentsToTypedEventRector::class);
    // Writes a handler return value into the event result instead of returning it.
    $rectorConfig->rule(HandlerReturnToEventResultRector::class);
    // Removes the deprecated $allowLegacyListeners property from subscriber plugins.
    $rectorConfig->rule(AllowLegacyListenersRector::class);
};
