<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla5
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Tests\Joomla5\HtmlViewGetToModelGetRector\Source\Model;

/**
 * Model stub for the HtmlViewGetToModelGetRector fixtures.
 */
class ArticlesModel extends AbstractListModel
{
    public function getFeatured()
    {
        return [];
    }
}
