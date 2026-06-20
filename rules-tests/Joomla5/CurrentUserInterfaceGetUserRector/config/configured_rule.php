<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla3
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\Rector\Joomla5\CurrentUserInterfaceGetUserRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
	$rectorConfig->rule(CurrentUserInterfaceGetUserRector::class);

	$rectorConfig->autoloadPaths([
		__DIR__ . '/../../../../joomla',
	]);
};
