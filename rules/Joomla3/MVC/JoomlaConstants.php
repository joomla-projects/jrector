<?php

/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2026 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Rector\Joomla3\MVC;

class JoomlaConstants
{
    /**
     * The acceptable folder names where component files can be placed in.
     *
     * @since 1.0.0
     * @var   string[]
     */
    public const ACCEPTABLE_CONTAINMENT_FOLDERS = ['admin', 'administrator', 'backend', 'site', 'frontend', 'components', 'api'];
}
