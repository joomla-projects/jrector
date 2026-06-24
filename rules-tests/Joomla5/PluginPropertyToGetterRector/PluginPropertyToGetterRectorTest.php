<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla5
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Tests\Joomla5\PluginPropertyToGetterRector;

use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * @since  1.0.0
 */
final class PluginPropertyToGetterRectorTest extends AbstractRectorTestCase
{
    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/configured_rule.php';
    }

    /**
     * @return \Iterator<array<int, string>>
     */
    public static function provideData(): \Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/Fixture');
    }

    /**
     * @dataProvider provideData()
     */
    public function testRefactor(string $fixtureFilePath): void
    {
        $this->doTestFile($fixtureFilePath);
    }
}
