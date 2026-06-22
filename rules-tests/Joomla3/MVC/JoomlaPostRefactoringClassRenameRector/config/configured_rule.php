<?php
/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2022 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare (strict_types=1);

use Joomla\Rector\Joomla3\MVC\JoomlaPostRefactoringClassRenameRector;
use Joomla\Rector\Joomla3\MVC\RenamedClassHandlerService;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
	$rectorConfig->singleton(RenamedClassHandlerService::class, static function () {
		return new RenamedClassHandlerService(realpath(__DIR__ . '/../'));
	});

	$rectorConfig->rule(JoomlaPostRefactoringClassRenameRector::class);
};
