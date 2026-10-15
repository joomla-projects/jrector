<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla7
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\Rector\Joomla7\ResultIsFalseablePropertyRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {

    $rectorConfig->rule(ResultIsFalseablePropertyRector::class);
};
