<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Set
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\Rector\Joomla6\CmsObjectReturnTypeRector;
use Joomla\Rector\Joomla6\HtmlViewExceptionHandlingRector;
use Joomla\Rector\Joomla6\SetErrorToExceptionRector;
use Joomla\Rector\Set\JoomlaSetList;
use Rector\Config\RectorConfig;

/**
 * Rules that bring code up to Joomla 6.
 *
 * This set includes the extension type specific sets. Use JOOMLA_6_PLUGINS, JOOMLA_6_MODULES or
 * JOOMLA_6_TEMPLATES directly if you only migrate one type of extension.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        JoomlaSetList::JOOMLA_6_PLUGINS,
        JoomlaSetList::JOOMLA_6_MODULES,
        JoomlaSetList::JOOMLA_6_TEMPLATES,
    ]);

    // Replaces CMSObject with stdClass in return type hints and @return PHPDoc tags.
    $rectorConfig->rule(CmsObjectReturnTypeRector::class);
    // Adds $model->setUseException(true) after $this->getModel() and removes getErrors() if-blocks.
    $rectorConfig->rule(HtmlViewExceptionHandlingRector::class);
    // Replaces $this->setError('msg') followed by return false with throw new \Exception('msg').
    $rectorConfig->rule(SetErrorToExceptionRector::class);
};
