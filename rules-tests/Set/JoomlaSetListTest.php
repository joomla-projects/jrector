<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Set
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Tests\Set;

use Joomla\Rector\Set\JoomlaSetList;
use PHPUnit\Framework\TestCase;

/**
 * Guards the set files against drifting away from the rule classes they register.
 *
 * @since  1.0.0
 */
final class JoomlaSetListTest extends TestCase
{
    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function provideSets(): \Iterator
    {
        $reflectionClass = new \ReflectionClass(JoomlaSetList::class);

        foreach ($reflectionClass->getConstants() as $name => $setFilePath) {
            yield $name => [$name, (string) $setFilePath];
        }
    }

    /**
     * @dataProvider provideSets()
     */
    public function testSetFileExists(string $constantName, string $setFilePath): void
    {
        $this->assertFileExists($setFilePath, \sprintf('Set %s points to a missing file.', $constantName));
    }

    /**
     * @dataProvider provideSets()
     */
    public function testSetFileReturnsClosure(string $constantName, string $setFilePath): void
    {
        $closure = require $setFilePath;

        $this->assertInstanceOf(
            \Closure::class,
            $closure,
            \sprintf('Set %s must return a closure taking a RectorConfig.', $constantName)
        );
    }

    /**
     * Every Joomla rule class imported by a set file must actually exist. Without this the set
     * would only blow up at runtime, in the user's project.
     *
     * @dataProvider provideSets()
     */
    public function testImportedRuleClassesExist(string $constantName, string $setFilePath): void
    {
        $source = (string) file_get_contents($setFilePath);

        preg_match_all('/^use (Joomla\\\\Rector\\\\[^;]+);$/m', $source, $matches);

        $importedClasses = array_filter(
            $matches[1],
            static fn (string $class): bool => $class !== JoomlaSetList::class
        );

        if ($importedClasses === []) {
            $this->assertTrue(true, \sprintf('Set %s registers no rule class yet.', $constantName));

            return;
        }

        foreach ($importedClasses as $importedClass) {
            $this->assertTrue(
                class_exists($importedClass),
                \sprintf('Set %s imports unknown class %s.', $constantName, $importedClass)
            );
        }
    }
}
