<?php
/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2022 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Naming\Rector\ClassMethod\JoomlaGetUserToGetCurrentUserRector;

return static function (RectorConfig $rectorConfig): void {
	$rectorConfig->ruleWithConfiguration(
		JoomlaGetUserToGetCurrentUserRector::class,
		['BaseModel']
	);
};
