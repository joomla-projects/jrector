<?php

/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2026 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Tests\Joomla3\MVC\HtmlViewToBaseHtmlViewRector;

use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * @since  1.0.0
 */
final class HtmlViewToBaseHtmlViewRectorTest extends AbstractRectorTestCase
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
    public function testRefactor(string $filePath): void
    {
        $this->doTestFile($filePath);
    }
}
