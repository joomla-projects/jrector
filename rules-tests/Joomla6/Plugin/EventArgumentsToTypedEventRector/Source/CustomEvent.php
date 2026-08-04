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
 * Stub for a project specific event class that is registered through the rule configuration.
 *
 * @since  1.0.0
 */
class CustomEvent
{
    public function getContext(): string
    {
        return '';
    }

    public function getItem(): object
    {
        return new \stdClass();
    }
}
