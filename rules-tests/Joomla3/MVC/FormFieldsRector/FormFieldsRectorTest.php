<?php

/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2022 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Rector\Tests\Naming\Rector\FileWithoutNamespace\FormFieldsRector;

use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * Unit Tests for the FormFieldsRector rule.
 *
 * @since  1.0.0
 */
final class FormFieldsRectorTest extends AbstractRectorTestCase
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
