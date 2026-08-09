<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla6
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla6\Plugin;

use Joomla\Rector\Joomla6\Event\JoomlaEventMap;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeVisitor;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces positional and named access to event arguments with the typed getters of the
 * concrete event class.
 *
 * Joomla 4 and 5 guaranteed through the `$legacyArgumentsOrder` property of the concrete event
 * classes that the order of the event arguments matches the parameter order of the old plugin
 * methods. Joomla 6 drops that guarantee, so code relying on the argument order breaks silently.
 *
 * Handled inside methods that receive a typed event parameter:
 *
 *   [$context, $item] = array_values($event->getArguments());  =>  individual getter assignments
 *   [$context, $item] = $event->getArguments();                =>  individual getter assignments
 *   $event->getArgument('context')                             =>  $event->getContext()
 *   $event['context']                                          =>  $event->getContext()
 *
 * The argument name => getter mapping cannot be derived from the argument name alone:
 * ContentPrepareEvent stores its argument as `subject` but exposes it as `getItem()`. The rule
 * therefore resolves the getters from a map generated from the Joomla core source
 * (see tools/generate-event-argument-map.php) and uses PHPStan reflection only to map a subclass
 * of a known event class onto its mapped ancestor.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla6\Plugin\EventArgumentsToTypedEventRector\EventArgumentsToTypedEventRectorTest
 */
final class EventArgumentsToTypedEventRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * Configuration key for additional or overriding event definitions.
     */
    public const EVENT_ARGUMENT_MAP = 'event_argument_map';

    /**
     * The effective map, i.e. the generated default map merged with the configured one.
     *
     * @var array<string, array{order: string[], getters: array<string, string>, resultAware: bool}>
     */
    private array $eventArgumentMap = JoomlaEventMap::DEFAULT_EVENT_ARGUMENT_MAP;

    public function __construct(
        private readonly JoomlaEventMap $eventMap,
    ) {
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public function configure(array $configuration): void
    {
        $this->eventArgumentMap = JoomlaEventMap::mergeConfiguration($configuration[self::EVENT_ARGUMENT_MAP] ?? []);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace positional and named event argument access with the typed getters of the concrete event class',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
use Joomla\CMS\Event\Content\ContentPrepareEvent;

class PlgContentExample extends CMSPlugin implements SubscriberInterface
{
    public function myOnContentPrepare(ContentPrepareEvent $event)
    {
        [$context, $item, $params, $page] = array_values($event->getArguments());

        if ($context !== 'com_content.article') {
            return;
        }

        $item->text .= 'foo';
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Joomla\CMS\Event\Content\ContentPrepareEvent;

class PlgContentExample extends CMSPlugin implements SubscriberInterface
{
    public function myOnContentPrepare(ContentPrepareEvent $event)
    {
        $context = $event->getContext();
        $item = $event->getItem();
        $params = $event->getParams();
        $page = $event->getPage();

        if ($context !== 'com_content.article') {
            return;
        }

        $item->text .= 'foo';
    }
}
CODE_SAMPLE,
                    [
                        self::EVENT_ARGUMENT_MAP => [
                            'Acme\\Event\\MyCustomEvent' => ['context', 'item'],
                        ],
                    ]
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($node->stmts === null || $node->stmts === []) {
            return null;
        }

        $eventVariables = $this->resolveEventVariables($node);

        if ($eventVariables === []) {
            return null;
        }

        $writeTargets = $this->collectArrayDimWriteTargets($node);

        $hasChanged = false;

        // The ClassMethod itself is traversed, not its statement list: replacing a statement
        // with several statements only survives when the traverser can write the new list
        // back into the owning node.
        $this->traverseNodesWithCallable(
            $node,
            function (Node $subNode) use ($node, $eventVariables, $writeTargets, &$hasChanged) {
                // A nested function that declares a parameter of the same name shadows the
                // event variable. Its body describes a different object, so skip the subtree.
                if ($subNode instanceof FunctionLike && $subNode !== $node) {
                    foreach ($subNode->getParams() as $param) {
                        if (
                            $param->var instanceof Variable
                            && \is_string($param->var->name)
                            && isset($eventVariables[$param->var->name])
                        ) {
                            return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                        }
                    }
                }

                if ($subNode instanceof Expression) {
                    $replacement = $this->refactorDestructuring($subNode, $eventVariables);

                    if ($replacement !== null) {
                        $hasChanged = true;

                        return $replacement;
                    }

                    return null;
                }

                if ($subNode instanceof MethodCall) {
                    $replacement = $this->refactorGetArgumentCall($subNode, $eventVariables);

                    if ($replacement !== null) {
                        $hasChanged = true;

                        return $replacement;
                    }

                    return null;
                }

                if ($subNode instanceof ArrayDimFetch && !$writeTargets->contains($subNode)) {
                    $replacement = $this->refactorArrayAccess($subNode, $eventVariables);

                    if ($replacement !== null) {
                        $hasChanged = true;

                        return $replacement;
                    }
                }

                return null;
            }
        );

        return $hasChanged ? $node : null;
    }

    // -------------------------------------------------------------------------
    // Event parameter resolution
    // -------------------------------------------------------------------------

    /**
     * Maps the name of every usable event parameter onto its argument definition.
     *
     * @return array<string, array{order: string[], getters: array<string, string>}>
     */
    private function resolveEventVariables(ClassMethod $classMethod): array
    {
        $eventVariables = [];

        foreach ($classMethod->params as $param) {
            // A by-reference parameter may be re-bound by the caller — leave it alone.
            if ($param->byRef || $param->variadic) {
                continue;
            }

            if (!$param->var instanceof Variable || !\is_string($param->var->name)) {
                continue;
            }

            // Only a plain class type identifies the event class. Nullable, union and
            // intersection types are skipped on purpose.
            if (!$param->type instanceof Name) {
                continue;
            }

            $className = $this->getName($param->type);

            if ($className === null) {
                continue;
            }

            $definition = $this->eventMap->resolveDefinition($this->eventArgumentMap, $className);

            if ($definition === null) {
                continue;
            }

            $eventVariables[$param->var->name] = $definition;
        }

        if ($eventVariables === []) {
            return [];
        }

        // Drop every event variable that is reassigned somewhere in the method — from that
        // point on it may no longer hold the event object.
        foreach ($this->collectReassignedVariables($classMethod) as $variableName) {
            unset($eventVariables[$variableName]);
        }

        return $eventVariables;
    }

    // -------------------------------------------------------------------------
    // Transformations
    // -------------------------------------------------------------------------

    /**
     * Rewrites `[$a, $b] = array_values($event->getArguments());` and
     * `[$a, $b] = $event->getArguments();` into one assignment per argument.
     *
     * @param array<string, array{order: string[], getters: array<string, string>}> $eventVariables
     *
     * @return Expression[]|null
     */
    private function refactorDestructuring(Expression $expression, array $eventVariables): ?array
    {
        if (!$expression->expr instanceof Assign) {
            return null;
        }

        $assign = $expression->expr;

        if (!$assign->var instanceof List_) {
            return null;
        }

        $eventVariableName = $this->matchGetArgumentsSource($assign->expr, $eventVariables);

        if ($eventVariableName === null) {
            return null;
        }

        $definition = $eventVariables[$eventVariableName];
        $order      = $definition['order'];

        if ($order === []) {
            return null;
        }

        $items = $assign->var->items;

        // More targets than known arguments — the argument list does not match, skip.
        if (\count($items) > \count($order)) {
            return null;
        }

        $targets = [];

        foreach ($items as $position => $item) {
            // Holes (`[$a, , $c]`) and keyed or by-reference targets are out of scope.
            if (!$item instanceof ArrayItem || $item->key !== null || $item->byRef) {
                return null;
            }

            // Nested destructuring cannot be mapped onto a single getter.
            if (!$item->value instanceof Variable && !$item->value instanceof PropertyFetch) {
                return null;
            }

            $argumentName = $order[$position];
            $getter       = $definition['getters'][$argumentName] ?? null;

            if ($getter === null) {
                return null;
            }

            $targets[] = [$item->value, $getter];
        }

        if ($targets === []) {
            return null;
        }

        $statements = [];

        foreach ($targets as $index => [$target, $getter]) {
            $assign = new Assign($target, new MethodCall(new Variable($eventVariableName), $getter));

            if ($index === 0) {
                // Reuse the original statement for the first assignment. It keeps its position
                // and its comments, so the surrounding blank lines and docblock survive.
                $expression->expr = $assign;
                $statements[]     = $expression;

                continue;
            }

            $statements[] = new Expression($assign);
        }

        return $statements;
    }

    /**
     * Returns the event variable name when the expression reads all event arguments,
     * i.e. `$event->getArguments()` or `array_values($event->getArguments())`.
     *
     * @param array<string, array{order: string[], getters: array<string, string>}> $eventVariables
     */
    private function matchGetArgumentsSource(Expr $expr, array $eventVariables): ?string
    {
        if ($expr instanceof FuncCall) {
            if (!$this->isName($expr, 'array_values') || \count($expr->args) !== 1) {
                return null;
            }

            $firstArg = $expr->args[0];

            if (!$firstArg instanceof Arg) {
                return null;
            }

            $expr = $firstArg->value;
        }

        if (!$expr instanceof MethodCall || $expr->args !== []) {
            return null;
        }

        if (!$this->isName($expr->name, 'getArguments')) {
            return null;
        }

        return $this->matchEventVariable($expr->var, $eventVariables);
    }

    /**
     * Rewrites `$event->getArgument('context')` into `$event->getContext()`.
     *
     * @param array<string, array{order: string[], getters: array<string, string>}> $eventVariables
     */
    private function refactorGetArgumentCall(MethodCall $methodCall, array $eventVariables): ?MethodCall
    {
        if (!$this->isName($methodCall->name, 'getArgument')) {
            return null;
        }

        // A second argument is a default value; the getter has no equivalent for it.
        if (\count($methodCall->args) !== 1) {
            return null;
        }

        $eventVariableName = $this->matchEventVariable($methodCall->var, $eventVariables);

        if ($eventVariableName === null) {
            return null;
        }

        $firstArg = $methodCall->args[0];

        if (!$firstArg instanceof Arg || !$firstArg->value instanceof String_) {
            return null;
        }

        $getter = $this->resolveGetter($firstArg->value->value, $eventVariables[$eventVariableName]);

        if ($getter === null) {
            return null;
        }

        return new MethodCall(new Variable($eventVariableName), $getter);
    }

    /**
     * Rewrites `$event['context']` into `$event->getContext()`.
     *
     * @param array<string, array{order: string[], getters: array<string, string>}> $eventVariables
     */
    private function refactorArrayAccess(ArrayDimFetch $arrayDimFetch, array $eventVariables): ?MethodCall
    {
        if (!$arrayDimFetch->dim instanceof String_) {
            return null;
        }

        $eventVariableName = $this->matchEventVariable($arrayDimFetch->var, $eventVariables);

        if ($eventVariableName === null) {
            return null;
        }

        $getter = $this->resolveGetter($arrayDimFetch->dim->value, $eventVariables[$eventVariableName]);

        if ($getter === null) {
            return null;
        }

        return new MethodCall(new Variable($eventVariableName), $getter);
    }

    /**
     * @param array{order: string[], getters: array<string, string>} $definition
     */
    private function resolveGetter(string $argumentName, array $definition): ?string
    {
        // Numeric access does not identify an argument reliably, see AbstractEvent::getArgument().
        if (is_numeric($argumentName)) {
            return null;
        }

        // The `result` argument belongs to the event result handling, not here.
        if ($argumentName === 'result') {
            return null;
        }

        return $definition['getters'][$argumentName] ?? null;
    }

    /**
     * @param array<string, array{order: string[], getters: array<string, string>}> $eventVariables
     */
    private function matchEventVariable(Expr $expr, array $eventVariables): ?string
    {
        if (!$expr instanceof Variable || !\is_string($expr->name)) {
            return null;
        }

        return isset($eventVariables[$expr->name]) ? $expr->name : null;
    }

    // -------------------------------------------------------------------------
    // Safety analysis
    // -------------------------------------------------------------------------

    /**
     * Collects the names of all variables that are written to inside the method.
     *
     * @return string[]
     */
    private function collectReassignedVariables(ClassMethod $classMethod): array
    {
        $variableNames = [];

        $this->traverseNodesWithCallable($classMethod->stmts, static function (Node $node) use (&$variableNames): ?Node {
            $target = null;

            if ($node instanceof Assign || $node instanceof AssignRef || $node instanceof AssignOp) {
                $target = $node->var;
            } elseif ($node instanceof PreInc || $node instanceof PreDec || $node instanceof PostInc || $node instanceof PostDec) {
                $target = $node->var;
            } elseif ($node instanceof Node\Stmt\Foreach_) {
                $target = $node->valueVar;
            }

            if ($target instanceof Variable && \is_string($target->name)) {
                $variableNames[] = $target->name;
            }

            // A by-reference assignment source may be rebound later as well.
            if ($node instanceof AssignRef && $node->expr instanceof Variable && \is_string($node->expr->name)) {
                $variableNames[] = $node->expr->name;
            }

            return null;
        });

        return $variableNames;
    }

    /**
     * Collects every ArrayDimFetch that is written to, unset or tested rather than read,
     * so `$event['x'] = 1;` or `isset($event['x'])` is never turned into a getter call.
     *
     * @return \SplObjectStorage<ArrayDimFetch, null>
     */
    private function collectArrayDimWriteTargets(ClassMethod $classMethod): \SplObjectStorage
    {
        /** @var \SplObjectStorage<ArrayDimFetch, null> $writeTargets */
        $writeTargets = new \SplObjectStorage();

        $mark = static function (?Expr $expr) use ($writeTargets): void {
            // Mark the whole chain, so `$event['a']['b'] = 1` also protects `$event['a']`.
            while ($expr instanceof ArrayDimFetch) {
                $writeTargets->attach($expr);
                $expr = $expr->var;
            }
        };

        $this->traverseNodesWithCallable($classMethod->stmts, static function (Node $node) use ($mark): ?Node {
            if ($node instanceof Assign || $node instanceof AssignRef || $node instanceof AssignOp) {
                $mark($node->var);
            }

            if ($node instanceof AssignRef) {
                $mark($node->expr);
            }

            if ($node instanceof PreInc || $node instanceof PreDec || $node instanceof PostInc || $node instanceof PostDec) {
                $mark($node->var);
            }

            if ($node instanceof Isset_) {
                foreach ($node->vars as $var) {
                    $mark($var);
                }
            }

            if ($node instanceof Unset_) {
                foreach ($node->vars as $var) {
                    $mark($var);
                }
            }

            if ($node instanceof Empty_) {
                $mark($node->expr);
            }

            return null;
        });

        return $writeTargets;
    }

}
