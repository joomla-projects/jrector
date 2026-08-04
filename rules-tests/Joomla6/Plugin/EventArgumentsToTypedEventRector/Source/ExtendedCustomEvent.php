<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla6
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Source;

/**
 * Stub for an event class that is not configured itself but inherits from a configured one.
 * Resolving it requires the PHPStan reflection fallback.
 *
 * @since  1.0.0
 */
class ExtendedCustomEvent extends CustomEvent
{
}
