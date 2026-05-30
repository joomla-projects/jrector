<?php
/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2022 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare (strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Naming\Config\JoomlaLegacyPrefixToNamespace;
use Rector\Naming\Rector\FileWithoutNamespace\JoomlaLegacyMVCToJ4Rector;
use Rector\Naming\Rector\FileWithoutNamespace\RenamedClassHandlerService;

return static function (RectorConfig $rectorConfig): void {
	$rectorConfig->singleton(RenamedClassHandlerService::class, static function () {
		return new RenamedClassHandlerService(realpath(__DIR__ . '/../../../../../../'));
	});

	$rectorConfig->ruleWithConfiguration(
		JoomlaLegacyMVCToJ4Rector::class,
		[
			new JoomlaLegacyPrefixToNamespace('Example', '\\Acme\\Example', [
				'Example',
				'ExampleFoobar',
			]),
		]
	);
};
