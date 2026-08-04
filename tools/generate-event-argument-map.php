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
 * Generates the default EVENT_ARGUMENT_MAP for EventArgumentsToTypedEventRector
 * from the Joomla core source tree.
 *
 * The map is NOT hand written. It is derived from `libraries/src/Event/**` by reading,
 * for every concrete event class:
 *
 *   - the `$legacyArgumentsOrder` property, which defines the positional order of the
 *     event arguments (position => argument name), and
 *   - every public `get*()` method whose body returns `$this->arguments['<name>']`,
 *     which defines the argument name => getter method mapping.
 *
 * Both are resolved along the inheritance chain, because e.g. ContentPrepareEvent
 * inherits getContext() from ContentEvent.
 *
 * Reading the getter bodies is essential: the getter name is NOT derivable from the
 * argument name. ContentPrepareEvent stores the argument as 'subject' but exposes it
 * as getItem().
 *
 * Usage:
 *   php tools/generate-event-argument-map.php [path/to/joomla] > map.php
 *
 * The default Joomla path is the `joomla/` directory at the repository root.
 */

$autoload = __DIR__ . '/../vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(\STDERR, "Run `composer install` first.\n");
    exit(1);
}

require $autoload;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

$joomlaRoot = $argv[1] ?? __DIR__ . '/../joomla';
$eventDir   = rtrim($joomlaRoot, '/\\') . '/libraries/src/Event';

if (!is_dir($eventDir)) {
    fwrite(\STDERR, "Event directory not found: $eventDir\n");
    exit(1);
}

$parser     = (new ParserFactory())->createForNewestSupportedVersion();
$nodeFinder = new NodeFinder();

/**
 * Raw per-class information, keyed by FQCN.
 *
 * @var array<string, array{parent: ?string, order: ?string[], getters: array<string, string>, abstract: bool}>
 */
$classes = [];

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($eventDir, FilesystemIterator::SKIP_DOTS));

/** @var SplFileInfo $file */
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $stmts = $parser->parse((string) file_get_contents($file->getPathname()));

    if ($stmts === null) {
        continue;
    }

    // Resolve the file's namespace and its use statements, so `extends ContentEvent`
    // and `extends AbstractImmutableEvent` can be turned into FQCNs.
    $namespace = '';
    $uses      = [];

    foreach ($nodeFinder->findInstanceOf($stmts, Namespace_::class) as $namespaceNode) {
        /** @var Namespace_ $namespaceNode */
        $namespace = $namespaceNode->name === null ? '' : $namespaceNode->name->toString();
    }

    foreach ($nodeFinder->findInstanceOf($stmts, Use_::class) as $useNode) {
        /** @var Use_ $useNode */
        foreach ($useNode->uses as $useUse) {
            $uses[$useUse->getAlias()->toString()] = $useUse->name->toString();
        }
    }

    $resolve = static function (string $name) use ($namespace, $uses): string {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $parts = explode('\\', $name);
        $first = array_shift($parts);

        if (isset($uses[$first])) {
            return $parts === [] ? $uses[$first] : $uses[$first] . '\\' . implode('\\', $parts);
        }

        return $namespace === '' ? $name : $namespace . '\\' . $name;
    };

    foreach ($nodeFinder->findInstanceOf($stmts, Class_::class) as $class) {
        /** @var Class_ $class */
        if ($class->name === null) {
            continue;
        }

        $fqcn = $namespace === '' ? $class->name->toString() : $namespace . '\\' . $class->name->toString();

        $classes[$fqcn] = [
            'parent'   => $class->extends === null ? null : $resolve($class->extends->toString()),
            'order'    => extractLegacyArgumentsOrder($class),
            'getters'  => extractGetters($class, $nodeFinder),
            'abstract' => $class->isAbstract(),
        ];
    }
}

/**
 * Reads the declared default value of `$legacyArgumentsOrder`.
 *
 * Returns null when the class does not declare the property at all (so the parent's
 * value applies), and an array — possibly empty — when it does.
 *
 * @return string[]|null
 */
function extractLegacyArgumentsOrder(Class_ $class): ?array
{
    foreach ($class->getProperties() as $property) {
        foreach ($property->props as $prop) {
            if ((string) $prop->name !== 'legacyArgumentsOrder') {
                continue;
            }

            if (!$prop->default instanceof Node\Expr\Array_) {
                return null;
            }

            $names = [];

            foreach ($prop->default->items as $item) {
                // Bail out on anything that is not a plain string literal list.
                if ($item === null || $item->key !== null || !$item->value instanceof String_) {
                    return null;
                }

                $names[] = $item->value->value;
            }

            return $names;
        }
    }

    return null;
}

/**
 * Collects `argument name => getter method name` for every public, parameterless
 * `get*()` method that returns `$this->arguments['<name>']`.
 *
 * @return array<string, string>
 */
function extractGetters(Class_ $class, NodeFinder $nodeFinder): array
{
    $getters = [];

    foreach ($class->getMethods() as $method) {
        if (!$method->isPublic() || $method->isAbstract() || $method->stmts === null) {
            continue;
        }

        $methodName = $method->name->toString();

        if (!str_starts_with($methodName, 'get') || $methodName === 'get') {
            continue;
        }

        // A getter that needs arguments is not a plain argument accessor.
        if ($method->params !== []) {
            continue;
        }

        foreach ($nodeFinder->findInstanceOf($method->stmts, Return_::class) as $return) {
            /** @var Return_ $return */
            if ($return->expr === null) {
                continue;
            }

            $argumentName = argumentNameFromExpr($return->expr, $nodeFinder);

            if ($argumentName === null) {
                continue;
            }

            // First matching return wins; later returns are usually fallbacks.
            $getters[$argumentName] ??= $methodName;

            break;
        }
    }

    return $getters;
}

/**
 * Extracts `<name>` from an expression that reads the argument, either directly as
 * `$this->arguments['<name>']` or indirectly as `$this->getArgument('<name>')`.
 */
function argumentNameFromExpr(Node\Expr $expr, NodeFinder $nodeFinder): ?string
{
    foreach ($nodeFinder->findInstanceOf($expr, Node\Expr\MethodCall::class) as $methodCall) {
        /** @var Node\Expr\MethodCall $methodCall */
        if (!$methodCall->var instanceof Variable || $methodCall->var->name !== 'this') {
            continue;
        }

        if (!$methodCall->name instanceof Identifier || $methodCall->name->toString() !== 'getArgument') {
            continue;
        }

        $firstArg = $methodCall->args[0] ?? null;

        if ($firstArg instanceof Node\Arg && $firstArg->value instanceof String_) {
            return $firstArg->value->value;
        }
    }

    foreach ($nodeFinder->findInstanceOf($expr, ArrayDimFetch::class) as $dimFetch) {
        /** @var ArrayDimFetch $dimFetch */
        if (!$dimFetch->var instanceof PropertyFetch || !$dimFetch->dim instanceof String_) {
            continue;
        }

        $propertyFetch = $dimFetch->var;

        if (!$propertyFetch->var instanceof Variable || $propertyFetch->var->name !== 'this') {
            continue;
        }

        if (!$propertyFetch->name instanceof Identifier || $propertyFetch->name->toString() !== 'arguments') {
            continue;
        }

        return $dimFetch->dim->value;
    }

    return null;
}

/**
 * Walks up the inheritance chain and returns the nearest declared value.
 *
 * @param array<string, array{parent: ?string, order: ?string[], getters: array<string, string>, abstract: bool}> $classes
 *
 * @return string[]
 */
function resolveOrder(string $fqcn, array $classes): array
{
    $seen = [];

    while (isset($classes[$fqcn]) && !isset($seen[$fqcn])) {
        $seen[$fqcn] = true;

        if ($classes[$fqcn]['order'] !== null && $classes[$fqcn]['order'] !== []) {
            return $classes[$fqcn]['order'];
        }

        $fqcn = (string) $classes[$fqcn]['parent'];
    }

    return [];
}

/**
 * Merges the getters of the whole inheritance chain, child declarations winning.
 *
 * @param array<string, array{parent: ?string, order: ?string[], getters: array<string, string>, abstract: bool}> $classes
 *
 * @return array<string, string>
 */
function resolveGetters(string $fqcn, array $classes): array
{
    $chain = [];
    $seen  = [];

    while (isset($classes[$fqcn]) && !isset($seen[$fqcn])) {
        $seen[$fqcn] = true;
        $chain[]     = $classes[$fqcn]['getters'];
        $fqcn        = (string) $classes[$fqcn]['parent'];
    }

    // Parents first, so child declarations overwrite them.
    $getters = [];

    foreach (array_reverse($chain) as $classGetters) {
        $getters = array_merge($getters, $classGetters);
    }

    return $getters;
}

$map      = [];
$warnings = [];

foreach (array_keys($classes) as $fqcn) {
    $order   = resolveOrder($fqcn, $classes);
    $getters = resolveGetters($fqcn, $classes);

    if ($getters === []) {
        continue;
    }

    // Truncate the positional order at the first argument without a getter. Keeping the
    // later positions would make every following position resolve to the wrong getter,
    // while dropping the order entirely would needlessly give up the leading positions.
    foreach ($order as $position => $argumentName) {
        if (!isset($getters[$argumentName])) {
            $warnings[] = \sprintf(
                '%s: argument "%s" has no getter — positional order truncated to %d entr%s.',
                $fqcn,
                $argumentName,
                $position,
                $position === 1 ? 'y' : 'ies'
            );

            $order = \array_slice($order, 0, $position);
            break;
        }
    }

    // The `result` argument belongs to the event result handling (task 03), not here.
    unset($getters['result']);

    if ($getters === []) {
        continue;
    }

    $map[$fqcn] = [
        'order'   => $order,
        'getters' => $getters,
    ];
}

ksort($map);

foreach ($warnings as $warning) {
    fwrite(\STDERR, $warning . "\n");
}

fwrite(\STDERR, \sprintf("Generated %d event classes from %s\n", \count($map), $eventDir));

// ---------------------------------------------------------------------------
// Output
// ---------------------------------------------------------------------------

$version     = 'unknown';
$versionFile = rtrim($joomlaRoot, '/\\') . '/libraries/src/Version.php';

if (is_file($versionFile)) {
    $versionSource = (string) file_get_contents($versionFile);

    if (
        preg_match('/MAJOR_VERSION\s*=\s*(\d+)/', $versionSource, $major)
        && preg_match('/MINOR_VERSION\s*=\s*(\d+)/', $versionSource, $minor)
        && preg_match('/PATCH_VERSION\s*=\s*(\d+)/', $versionSource, $patch)
    ) {
        $version = $major[1] . '.' . $minor[1] . '.' . $patch[1];
    }
}

echo "    /**\n";
echo "     * Default argument map for the Joomla core events.\n";
echo "     *\n";
echo "     * Generated by tools/generate-event-argument-map.php from Joomla $version.\n";
echo "     * Do not edit by hand — re-run the generator instead.\n";
echo "     *\n";
echo "     * @var array<class-string, array{order: string[], getters: array<string, string>}>\n";
echo "     */\n";
echo "    private const DEFAULT_EVENT_ARGUMENT_MAP = [\n";

foreach ($map as $fqcn => $definition) {
    echo "        '" . str_replace('\\', '\\\\', $fqcn) . "' => [\n";
    echo "            'order'   => [" . implode(', ', array_map(
        static fn (string $name): string => "'" . $name . "'",
        $definition['order']
    )) . "],\n";
    echo "            'getters' => [";

    $pairs = [];

    foreach ($definition['getters'] as $argumentName => $getter) {
        $pairs[] = "'" . $argumentName . "' => '" . $getter . "'";
    }

    echo implode(', ', $pairs) . "],\n";
    echo "        ],\n";
}

echo "    ];\n";
