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
 * Parent model stub, used to verify that inherited getters are detected.
 */
abstract class AbstractListModel
{
    public function getItems()
    {
        return [];
    }

    public function getPagination()
    {
        return null;
    }

    public function getTotal()
    {
        return 0;
    }
}
