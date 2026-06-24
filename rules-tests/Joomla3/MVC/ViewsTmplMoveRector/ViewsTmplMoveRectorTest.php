<?php

/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2026 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Tests\Joomla3\MVC\ViewsTmplMoveRector;

use Joomla\Rector\Joomla3\MVC\FileRenameCollectorService;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * Unit tests for the ViewsTmplMoveRector rule.
 *
 * The rule does not transform PHP AST, it only registers file renames in
 * FileRenameCollectorService. Each test therefore verifies two things:
 *   1. The file content is unchanged after running the rule.
 *   2. The service recorded the expected source → destination rename.
 *
 * @since  1.0.0
 */
final class ViewsTmplMoveRectorTest extends AbstractRectorTestCase
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
     * Verifies that files inside views/<view>/tmpl/ are not altered by the rule
     * (their content stays the same – the only side-effect is the rename entry).
     *
     * @dataProvider provideData()
     */
    public function testFileContentIsUnchanged(string $fixtureFilePath): void
    {
        $this->doTestFile($fixtureFilePath);
    }

    /**
     * Verifies that processing a file in admin/views/example/tmpl/ registers a
     * rename entry that moves it to admin/tmpl/example/.
     */
    public function testAdminViewTmplRenameIsRegistered(): void
    {
        $fixture = __DIR__ . '/Fixture/admin/views/example/tmpl/default.php.inc';
        $this->doTestFile($fixture);

        /** @var FileRenameCollectorService $service */
        $service = $this->make(FileRenameCollectorService::class);
        $renames = $service->getRenames();

        $this->assertNotEmpty($renames, 'FileRenameCollectorService should contain at least one rename entry.');

        $found = false;

        foreach ($renames as $pairs) {
            foreach ($pairs as $from => $to) {
                $from = str_replace('\\', '/', $from);
                $to   = str_replace('\\', '/', $to);

                if (str_contains($from, '/views/example/tmpl/') && str_contains($to, '/tmpl/example/')) {
                    $found = true;
                    break 2;
                }
            }
        }

        $this->assertTrue($found, 'Expected a rename from views/example/tmpl/ to tmpl/example/ but none was found.');
    }

    /**
     * Verifies that processing a file in site/views/foobar/tmpl/ registers a
     * rename entry that moves it to site/tmpl/foobar/.
     */
    public function testSiteViewTmplRenameIsRegistered(): void
    {
        $fixture = __DIR__ . '/Fixture/site/views/foobar/tmpl/default.php.inc';
        $this->doTestFile($fixture);

        /** @var FileRenameCollectorService $service */
        $service = $this->make(FileRenameCollectorService::class);
        $renames = $service->getRenames();

        $this->assertNotEmpty($renames, 'FileRenameCollectorService should contain at least one rename entry.');

        $found = false;

        foreach ($renames as $pairs) {
            foreach ($pairs as $from => $to) {
                $from = str_replace('\\', '/', $from);
                $to   = str_replace('\\', '/', $to);

                if (str_contains($from, '/views/foobar/tmpl/') && str_contains($to, '/tmpl/foobar/')) {
                    $found = true;
                    break 2;
                }
            }
        }

        $this->assertTrue($found, 'Expected a rename from views/foobar/tmpl/ to tmpl/foobar/ but none was found.');
    }
}
