<?php
/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2022 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Rector\Tests\Naming\Rector\ClassMethod\JoomlaHtmlViewExceptionHandlingRector;

use Iterator;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * @since  1.0.0
 */
final class JoomlaHtmlViewExceptionHandlingRectorTest extends AbstractRectorTestCase
{
	public function provideConfigFilePath(): string
	{
		return __DIR__ . '/config/configured_rule.php';
	}

	/**
	 * @return Iterator<array<int, string>>
	 */
	public function provideData(): Iterator
	{
		return $this->yieldFilesFromDirectory(__DIR__ . '/Fixture');
	}

	/**
	 * @dataProvider provideData()
	 */
	public function testRefactor(string $fixtureFilePath): void
	{
		$this->doTestFile($fixtureFilePath);
	}
}