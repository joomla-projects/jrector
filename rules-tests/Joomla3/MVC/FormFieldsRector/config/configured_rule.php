<?php

/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2022 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare (strict_types=1);

use Joomla\Rector\Joomla3\MVC\Config\JoomlaLegacyPrefixToNamespace;
use Joomla\Rector\Joomla3\MVC\FileRenameCollectorService;
use Joomla\Rector\Joomla3\MVC\FormFieldsRector;
use Joomla\Rector\Joomla3\MVC\RenamedClassHandlerService;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->singleton(RenamedClassHandlerService::class, static function () {
        return new RenamedClassHandlerService(realpath(__DIR__ . '/../../../../../../'));
    });

    $rectorConfig->singleton(FileRenameCollectorService::class, static function () {
        return new FileRenameCollectorService();
    });

    $rectorConfig->ruleWithConfiguration(
        FormFieldsRector::class,
        [
            new JoomlaLegacyPrefixToNamespace('Example', '\\Acme\\Example', []),
        ]
    );
};
