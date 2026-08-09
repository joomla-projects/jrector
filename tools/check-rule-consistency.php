<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Tools
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

/**
 * Checks that every Rector rule under `rules/` is actually reachable for users:
 *
 *   1. it has a documentation section in docs/rules/joomla<N>.md (or is covered by docs/mvc.md),
 *   2. it is listed in the rule index docs/rules.md,
 *   3. it appears in the example configuration assets/rector.php.
 *
 * Without this check the three drift apart as soon as the number of rules grows: a rule gets
 * written, and nobody notices that no user can find it.
 *
 * Usage:
 *   php tools/check-rule-consistency.php
 *
 * Exits with 1 and a list of problems if anything is missing.
 */

$root = \dirname(__DIR__);

/**
 * Rules that are intentionally not offered to users directly.
 *
 * Keep this list short and justify every entry. It is an escape hatch, not a place to park
 * undocumented work.
 */
const INTERNAL_RULES = [
    // Applied by the Joomla 3 MVC pipeline through RenamedClassHandlerService, driven by the
    // generated class map rather than configured by the user. Documented as part of docs/mvc.md.
    'JoomlaPostRefactoringClassRenameRector',
];

$ruleFiles = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/rules', FilesystemIterator::SKIP_DOTS)
);

/** @var SplFileInfo $file */
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    if (!str_ends_with($file->getBasename('.php'), 'Rector')) {
        continue;
    }

    $ruleFiles[] = $file->getPathname();
}

sort($ruleFiles);

if ($ruleFiles === []) {
    fwrite(\STDERR, "No rule classes found under rules/.\n");
    exit(1);
}

$assetsConfig = (string) file_get_contents($root . '/assets/rector.php');
$ruleIndex    = (string) file_get_contents($root . '/docs/rules.md');
$mvcDoc       = is_file($root . '/docs/mvc.md') ? (string) file_get_contents($root . '/docs/mvc.md') : '';

$problems = [];
$checked  = 0;
$skipped  = 0;

foreach ($ruleFiles as $ruleFile) {
    $shortName = basename($ruleFile, '.php');

    if (\in_array($shortName, INTERNAL_RULES, true)) {
        $skipped++;
        continue;
    }

    $checked++;

    $relativePath = str_replace('\\', '/', substr($ruleFile, \strlen($root) + 1));

    // The Joomla version comes from the namespace, e.g. rules/Joomla6/Plugin/FooRector.php.
    if (!preg_match('#^rules/Joomla(\d)/#', $relativePath, $matches)) {
        $problems[] = \sprintf('%s: cannot determine the Joomla version from the path.', $relativePath);
        continue;
    }

    $version     = $matches[1];
    $versionDoc  = $root . '/docs/rules/joomla' . $version . '.md';
    $documented  = false;

    if (is_file($versionDoc)) {
        $documented = preg_match('/^## ' . preg_quote($shortName, '/') . '$/m', (string) file_get_contents($versionDoc)) === 1;
    }

    // The structural Joomla 3 MVC rules are documented in the MVC guide instead.
    if (!$documented && str_contains($mvcDoc, $shortName)) {
        $documented = true;
    }

    if (!$documented) {
        $problems[] = \sprintf(
            '%s: no "## %s" section in docs/rules/joomla%s.md and not mentioned in docs/mvc.md.',
            $relativePath,
            $shortName,
            $version
        );
    }

    if (!str_contains($ruleIndex, $shortName)) {
        $problems[] = \sprintf('%s: not listed in the rule index docs/rules.md.', $relativePath);
    }

    if (!str_contains($assetsConfig, $shortName)) {
        $problems[] = \sprintf('%s: no entry in assets/rector.php.', $relativePath);
    }
}

if ($problems !== []) {
    fwrite(\STDERR, "Rule consistency check failed:\n\n");

    foreach ($problems as $problem) {
        fwrite(\STDERR, '  - ' . $problem . "\n");
    }

    fwrite(\STDERR, \sprintf("\n%d problem(s) in %d rule(s).\n", \count($problems), $checked));

    exit(1);
}

echo \sprintf(
    "Rule consistency check passed: %d rule(s) documented and configured, %d internal rule(s) skipped.\n",
    $checked,
    $skipped
);
