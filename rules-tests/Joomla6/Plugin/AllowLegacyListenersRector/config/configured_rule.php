<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla6
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\Rector\Joomla6\Plugin\AllowLegacyListenersRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    // Minimal CMSPlugin / SubscriberInterface stubs, so the reflection path can be tested
    // without requiring a real Joomla installation. In a real project this is what
    // autoloadPaths([__DIR__ . '/joomla']) provides.
    $rectorConfig->autoloadPaths([__DIR__ . '/../JoomlaStub']);

    // Default mode: remove.
    $rectorConfig->rule(AllowLegacyListenersRector::class);
};
