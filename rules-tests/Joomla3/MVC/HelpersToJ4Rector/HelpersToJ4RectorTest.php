<?php

/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2022 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Tests\Joomla3\MVC\HelpersToJ4Rector;

use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * Unit Tests for the JoomlaHelpersToJ4Rector rule.
 *
 * @since  1.0.0
 */
final class HelpersToJ4RectorTest extends AbstractRectorTestCase
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
    public function testRefactorNamespace(string $fixtureFilePath): void
    {
        $this->doTestFile($fixtureFilePath);
    }
}
