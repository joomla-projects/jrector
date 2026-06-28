<?php

/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2026 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\Rector\Joomla3\MVC\HtmlViewToBaseHtmlViewRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(HtmlViewToBaseHtmlViewRector::class);
};
