<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Set
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Set;

/**
 * Ready-made sets of the Joomla Rector rules.
 *
 * Sets are meant for the second pass over a code base and for continuous integration, not for
 * the first migration. Run the rules one at a time and review each change while migrating; use
 * a set once the code base is clean, to keep it clean.
 *
 * @since  1.0.0
 */
final class JoomlaSetList
{
    /**
     * Rules that bring Joomla 3 code up to Joomla 3.10.
     *
     * The Joomla3\MVC rules are deliberately not part of this set — they need a per-project
     * namespace configuration and they move files. See docs/mvc.md.
     */
    public const JOOMLA_3 = __DIR__ . '/../../config/sets/joomla3.php';

    /**
     * Rules that bring code up to Joomla 4.
     */
    public const JOOMLA_4 = __DIR__ . '/../../config/sets/joomla4.php';

    /**
     * Rules that bring code up to Joomla 5.
     */
    public const JOOMLA_5 = __DIR__ . '/../../config/sets/joomla5.php';

    /**
     * Rules that bring code up to Joomla 6, including the extension type specific sets below.
     */
    public const JOOMLA_6 = __DIR__ . '/../../config/sets/joomla6.php';

    /**
     * Joomla 6 rules that only concern plugins.
     */
    public const JOOMLA_6_PLUGINS = __DIR__ . '/../../config/sets/joomla6-plugins.php';

    /**
     * Joomla 6 rules that only concern modules.
     */
    public const JOOMLA_6_MODULES = __DIR__ . '/../../config/sets/joomla6-modules.php';

    /**
     * Joomla 6 rules that only concern templates.
     */
    public const JOOMLA_6_TEMPLATES = __DIR__ . '/../../config/sets/joomla6-templates.php';

    /**
     * Everything from Joomla 3 up to Joomla 6 in one set.
     *
     * Structural rules that create or move files are not included, for the same reason the
     * Joomla3\MVC rules are not: they change the layout of an extension and have to be run
     * deliberately, not as part of a cumulative upgrade.
     */
    public const UP_TO_JOOMLA_6 = __DIR__ . '/../../config/sets/up-to-joomla6.php';
}
