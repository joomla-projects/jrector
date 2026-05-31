<?php
/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2022 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Naming\Rector\ClassMethod\JoomlaHtmlViewGetToModelGetRector;

return static function (RectorConfig $rectorConfig): void {
	$rectorConfig->ruleWithConfiguration(
		JoomlaHtmlViewGetToModelGetRector::class,
		['HtmlView']
	);
};