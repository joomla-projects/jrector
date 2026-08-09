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

// Positional arguments only; flags such as --write are handled separately.
$positional = array_values(array_filter(
    \array_slice($argv, 1),
    static fn (string $argument): bool => !str_starts_with($argument, '-')
));

$joomlaRoot = $positional[0] ?? __DIR__ . '/../joomla';
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
            'parent'      => $class->extends === null ? null : $resolve($class->extends->toString()),
            'order'       => extractLegacyArgumentsOrder($class),
            'getters'     => extractGetters($class, $nodeFinder),
            'abstract'    => $class->isAbstract(),
            'resultAware' => isResultAware($class, $nodeFinder),
        ];
    }
}

/**
 * Tells whether the class carries the event result API, i.e. whether `addResult()` exists.
 *
 * Only events implementing ResultAwareInterface (in practice via the ResultAware trait) have it,
 * so a handler for any other event must not be rewritten to addResult().
 */
function isResultAware(Class_ $class, NodeFinder $nodeFinder): bool
{
    foreach ($class->implements as $interface) {
        if ($interface->getLast() === 'ResultAwareInterface') {
            return true;
        }
    }

    foreach ($nodeFinder->findInstanceOf($class->stmts, Node\Stmt\TraitUse::class) as $traitUse) {
        /** @var Node\Stmt\TraitUse $traitUse */
        foreach ($traitUse->traits as $trait) {
            if ($trait->getLast() === 'ResultAware') {
                return true;
            }
        }
    }

    return false;
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
 * True when the class or any of its ancestors carries the result API.
 *
 * @param array<string, array{parent: ?string, order: ?string[], getters: array<string, string>, abstract: bool, resultAware: bool}> $classes
 */
function resolveResultAware(string $fqcn, array $classes): bool
{
    $seen = [];

    while (isset($classes[$fqcn]) && !isset($seen[$fqcn])) {
        $seen[$fqcn] = true;

        if ($classes[$fqcn]['resultAware']) {
            return true;
        }

        $fqcn = (string) $classes[$fqcn]['parent'];
    }

    return false;
}

/**
 * Reads the `$eventNameToConcreteClass` map from CoreEventAware, i.e. the mapping every
 * `AbstractEvent::create()` call uses to turn an event name into a concrete event class.
 *
 * @return array<string, string>
 */
function extractEventNameMap(string $eventDir, \PhpParser\Parser $parser, NodeFinder $nodeFinder): array
{
    $file = $eventDir . '/CoreEventAware.php';

    if (!is_file($file)) {
        fwrite(\STDERR, "CoreEventAware.php not found, event name map will be empty.\n");

        return [];
    }

    $stmts = $parser->parse((string) file_get_contents($file));

    if ($stmts === null) {
        return [];
    }

    $namespace = '';

    foreach ($nodeFinder->findInstanceOf($stmts, Namespace_::class) as $namespaceNode) {
        /** @var Namespace_ $namespaceNode */
        $namespace = $namespaceNode->name === null ? '' : $namespaceNode->name->toString();
    }

    $map = [];

    foreach ($nodeFinder->findInstanceOf($stmts, Node\Stmt\Property::class) as $property) {
        /** @var Node\Stmt\Property $property */
        foreach ($property->props as $prop) {
            if ((string) $prop->name !== 'eventNameToConcreteClass' || !$prop->default instanceof Node\Expr\Array_) {
                continue;
            }

            foreach ($prop->default->items as $item) {
                if ($item === null || !$item->key instanceof String_) {
                    continue;
                }

                // Values are written as `Application\BeforeExecuteEvent::class`, relative to
                // the CoreEventAware namespace.
                if (!$item->value instanceof Node\Expr\ClassConstFetch || !$item->value->class instanceof Node\Name) {
                    continue;
                }

                if (!$item->value->name instanceof Identifier || $item->value->name->toString() !== 'class') {
                    continue;
                }

                $className = $item->value->class->toString();

                if (str_starts_with($className, '\\')) {
                    $className = ltrim($className, '\\');
                } elseif ($className === 'Event') {
                    // The Joomla\Event\Event fallback is not a concrete event class.
                    continue;
                } else {
                    $className = $namespace . '\\' . $className;
                }

                $map[$item->key->value] = $className;
            }
        }
    }

    return $map;
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
        'order'       => $order,
        'getters'     => $getters,
        'resultAware' => resolveResultAware($fqcn, $classes),
    ];
}

ksort($map);

$eventNameMap = extractEventNameMap($eventDir, $parser, $nodeFinder);

// Only keep names that point at a class we actually know the arguments of.
foreach (array_keys($eventNameMap) as $eventName) {
    if (!isset($map[$eventNameMap[$eventName]])) {
        unset($eventNameMap[$eventName]);
    }
}

ksort($eventNameMap);

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

$argumentMapCode = "    /**\n"
    . "     * Argument map for the Joomla core events.\n"
    . "     *\n"
    . "     * `order` is the positional argument order taken from \$legacyArgumentsOrder, `getters`\n"
    . "     * maps an argument name onto its getter, and `resultAware` says whether the event\n"
    . "     * carries the addResult() API of ResultAwareInterface.\n"
    . "     *\n"
    . "     * Generated by tools/generate-event-argument-map.php from Joomla $version.\n"
    . "     * Do not edit by hand — re-run the generator instead.\n"
    . "     *\n"
    . "     * @var array<class-string, array{order: string[], getters: array<string, string>, resultAware: bool}>\n"
    . "     */\n"
    . "    public const DEFAULT_EVENT_ARGUMENT_MAP = [\n";

foreach ($map as $fqcn => $definition) {
    $pairs = [];

    foreach ($definition['getters'] as $argumentName => $getter) {
        $pairs[] = "'" . $argumentName . "' => '" . $getter . "'";
    }

    $argumentMapCode .= "        '" . str_replace('\\', '\\\\', $fqcn) . "' => [\n"
        . "            'order'       => [" . implode(', ', array_map(
            static fn (string $name): string => "'" . $name . "'",
            $definition['order']
        )) . "],\n"
        . "            'getters'     => [" . implode(', ', $pairs) . "],\n"
        . "            'resultAware' => " . ($definition['resultAware'] ? 'true' : 'false') . ",\n"
        . "        ],\n";
}

$argumentMapCode .= "    ];";

$nameMapCode = "    /**\n"
    . "     * Maps a Joomla core event name onto its concrete event class.\n"
    . "     *\n"
    . "     * Taken from CoreEventAware::\$eventNameToConcreteClass, reduced to the events whose\n"
    . "     * arguments are known in DEFAULT_EVENT_ARGUMENT_MAP.\n"
    . "     *\n"
    . "     * Generated by tools/generate-event-argument-map.php from Joomla $version.\n"
    . "     * Do not edit by hand — re-run the generator instead.\n"
    . "     *\n"
    . "     * @var array<string, class-string>\n"
    . "     */\n"
    . "    public const DEFAULT_EVENT_NAME_MAP = [\n";

foreach ($eventNameMap as $eventName => $fqcn) {
    $nameMapCode .= "        '" . $eventName . "' => '" . str_replace('\\', '\\\\', $fqcn) . "',\n";
}

$nameMapCode .= "    ];";

fwrite(\STDERR, \sprintf("Event name map: %d entries\n", \count($eventNameMap)));

// ---------------------------------------------------------------------------
// Write the maps into the shared map class, between the generated markers.
// ---------------------------------------------------------------------------

$target = \dirname(__DIR__) . '/rules/Joomla6/Event/JoomlaEventMap.php';

if (!\in_array('--write', $argv, true)) {
    echo $argumentMapCode . "\n\n" . $nameMapCode . "\n";
    fwrite(\STDERR, "\nRun with --write to update $target in place.\n");

    exit(0);
}

if (!is_file($target)) {
    fwrite(\STDERR, "Target not found: $target\n");
    exit(1);
}

$source = (string) file_get_contents($target);

foreach ([
    'ARGUMENT_MAP'   => $argumentMapCode,
    'EVENT_NAME_MAP' => $nameMapCode,
] as $marker => $code) {
    $open    = '    // <generated:' . $marker . '>';
    $close   = '    // </generated:' . $marker . '>';
    $pattern = '/' . preg_quote($open, '/') . '\r?\n.*?' . preg_quote($close, '/') . '/s';

    if (preg_match($pattern, $source) !== 1) {
        fwrite(\STDERR, "Marker <generated:$marker> not found in $target\n");
        exit(1);
    }

    $replacement = $open . "\n" . $code . "\n" . $close;

    $source = (string) preg_replace_callback(
        $pattern,
        static fn (array $matches): string => $replacement,
        $source
    );
}

file_put_contents($target, $source);

fwrite(\STDERR, "Wrote both maps into $target\n");
