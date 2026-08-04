<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Set
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\Rector\Joomla4\JimportRector;
use Rector\Config\RectorConfig;

/**
 * Rules that bring code up to Joomla 4.
 */
return static function (RectorConfig $rectorConfig): void {
    // Removes jimport('joomla.*') calls that are no longer needed in Joomla 4.
    $rectorConfig->rule(JimportRector::class);
};
