<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla6
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Tests\Joomla6\Plugin\AllowLegacyListenersRector;

use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * Covers the opt-in `set_false` mode.
 *
 * @since  1.0.0
 */
final class AllowLegacyListenersSetFalseModeTest extends AbstractRectorTestCase
{
    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/set_false_mode.php';
    }

    /**
     * @return \Iterator<array<int, string>>
     */
    public static function provideData(): \Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/FixtureSetFalse');
    }

    /**
     * @dataProvider provideData()
     */
    public function testRefactor(string $fixtureFilePath): void
    {
        $this->doTestFile($fixtureFilePath);
    }
}
