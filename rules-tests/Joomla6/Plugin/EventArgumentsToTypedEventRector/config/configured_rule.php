<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla6
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\Rector\Joomla6\Plugin\EventArgumentsToTypedEventRector;
use Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\Source\CustomEvent;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->ruleWithConfiguration(EventArgumentsToTypedEventRector::class, [
        EventArgumentsToTypedEventRector::EVENT_ARGUMENT_MAP => [
            CustomEvent::class => ['context', 'item'],
        ],
    ]);
};
