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
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeVisitor;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Writes a plugin handler's return value into the event result instead of returning it.
 *
 * When a handler is called through the event dispatcher its return value is discarded, so a
 * `return true;` silently stops working — it does not crash, it just has no effect any more.
 *
 * The canonical method is `addResult()`, verified against Joomla 6.1.1: it is declared in
 * `libraries/src/Event/Result/ResultAwareInterface.php` and implemented by the `ResultAware`
 * trait in the same folder. `updateEventResult()` exists on a single event (Plugin\AjaxEvent)
 * and is therefore not the generic form.
 *
 * Because `addResult()` only exists on events implementing `ResultAwareInterface`, the rule
 * only converts handlers whose event class is marked result aware in the generated event map.
 * Rewriting any other handler would produce a call to a method that does not exist.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla6\Plugin\HandlerReturnToEventResultRector\HandlerReturnToEventResultRectorTest
 */
final class HandlerReturnToEventResultRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * Configuration key for additional or overriding event definitions.
     */
    public const EVENT_ARGUMENT_MAP = 'event_argument_map';

    private const PLUGIN_ANCESTOR = 'Joomla\\CMS\\Plugin\\CMSPlugin';

    /**
     * @var array<string, array{order: string[], getters: array<string, string>, resultAware: bool}>
     */
    private array $eventArgumentMap = JoomlaEventMap::DEFAULT_EVENT_ARGUMENT_MAP;

    public function __construct(
        private readonly JoomlaEventMap $eventMap,
        private readonly ReflectionProvider $reflectionProvider,
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
            'Write a plugin handler return value into the event result instead of returning it',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
public function onUserAuthenticate(AuthenticationEvent $event)
{
    $credentials = $event->getCredentials();

    if (!$this->check($credentials)) {
        return false;
    }

    return true;
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
public function onUserAuthenticate(AuthenticationEvent $event): void
{
    $credentials = $event->getCredentials();

    if (!$this->check($credentials)) {
        $event->addResult(false);

        return;
    }

    $event->addResult(true);
}
CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($node->isAnonymous() || !$this->isCmsPluginClass($node)) {
            return null;
        }

        $subscribedMethods = $this->collectSubscribedMethodNames($node);

        $hasChanged = false;

        foreach ($node->getMethods() as $method) {
            if ($this->refactorHandler($method, $subscribedMethods)) {
                $hasChanged = true;
            }
        }

        return $hasChanged ? $node : null;
    }

    // -------------------------------------------------------------------------

    /**
     * @param string[] $subscribedMethods
     */
    private function refactorHandler(ClassMethod $method, array $subscribedMethods): bool
    {
        if ($method->stmts === null || !$method->isPublic()) {
            return false;
        }

        $methodName = $method->name->toString();

        // Only event handlers, so ordinary helper methods are never touched.
        if (!str_starts_with($methodName, 'on') && !\in_array($methodName, $subscribedMethods, true)) {
            return false;
        }

        $eventVariableName = $this->resolveResultAwareEventVariable($method);

        if ($eventVariableName === null) {
            return false;
        }

        // Idempotence: the handler already reports its result through the event.
        if ($this->alreadyUsesEventResult($method, $eventVariableName)) {
            return false;
        }

        $lastStatement = $method->stmts === [] ? null : $method->stmts[\count($method->stmts) - 1];
        $hasChanged    = false;

        $this->traverseNodesWithCallable(
            $method,
            function (Node $subNode) use ($method, $eventVariableName, $lastStatement, &$hasChanged) {
                // A return inside a closure belongs to that closure, not to the handler.
                if ($subNode instanceof FunctionLike && $subNode !== $method) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                if (!$subNode instanceof Return_ || $subNode->expr === null) {
                    return null;
                }

                $hasChanged = true;

                $addResult = new Expression(
                    new MethodCall(
                        new Variable($eventVariableName),
                        'addResult',
                        [new Arg($subNode->expr)]
                    )
                );

                $addResult->setAttribute('comments', $subNode->getComments());

                // A trailing return needs no replacement statement — the method just ends.
                if ($subNode === $lastStatement) {
                    return [$addResult];
                }

                return [$addResult, new Return_()];
            }
        );

        if (!$hasChanged) {
            return false;
        }

        $method->returnType = new Identifier('void');
        $this->rewriteReturnTag($method);

        return true;
    }

    /**
     * Returns the name of the event parameter when the handler has exactly one resolvable,
     * result aware event parameter.
     */
    private function resolveResultAwareEventVariable(ClassMethod $method): ?string
    {
        foreach ($method->params as $param) {
            if ($param->byRef || $param->variadic || !$param->var instanceof Variable) {
                continue;
            }

            if (!\is_string($param->var->name) || !$param->type instanceof Name) {
                continue;
            }

            $className = $this->getName($param->type);

            if ($className === null) {
                continue;
            }

            $definition = $this->eventMap->resolveDefinition($this->eventArgumentMap, $className);

            // addResult() only exists on ResultAwareInterface events.
            if ($definition === null || !$definition['resultAware']) {
                continue;
            }

            return $param->var->name;
        }

        return null;
    }

    private function alreadyUsesEventResult(ClassMethod $method, string $eventVariableName): bool
    {
        $alreadyConverted = false;

        $this->traverseNodesWithCallable($method, static function (Node $node) use ($eventVariableName, &$alreadyConverted): ?Node {
            if (!$node instanceof MethodCall || !$node->var instanceof Variable) {
                return null;
            }

            if ($node->var->name !== $eventVariableName || !$node->name instanceof Identifier) {
                return null;
            }

            $calledMethod = $node->name->toString();

            if ($calledMethod === 'addResult' || $calledMethod === 'updateEventResult') {
                $alreadyConverted = true;
            }

            return null;
        });

        return $alreadyConverted;
    }

    /**
     * Rewrites an existing `@return` tag to `@return void`, leaving the rest of the docblock alone.
     */
    private function rewriteReturnTag(ClassMethod $method): void
    {
        $docComment = $method->getDocComment();

        if (!$docComment instanceof Doc) {
            return;
        }

        $text = $docComment->getText();

        if (!str_contains($text, '@return')) {
            return;
        }

        $updated = preg_replace('/@return\s+\S+/', '@return void', $text, 1);

        if (\is_string($updated) && $updated !== $text) {
            $method->setDocComment(new Doc($updated));
        }
    }

    /**
     * Collects the handler method names declared in getSubscribedEvents().
     *
     * @return string[]
     */
    private function collectSubscribedMethodNames(Class_ $class): array
    {
        $method = $class->getMethod('getSubscribedEvents');

        if (!$method instanceof ClassMethod || $method->stmts === null) {
            return [];
        }

        $methodNames = [];

        $this->traverseNodesWithCallable($method, static function (Node $node) use (&$methodNames): ?Node {
            if (!$node instanceof ArrayItem || !$node->value instanceof String_) {
                return null;
            }

            $methodNames[] = $node->value->value;

            return null;
        });

        return $methodNames;
    }

    private function isCmsPluginClass(Class_ $class): bool
    {
        if ($class->extends === null) {
            return false;
        }

        if (
            ltrim($class->extends->toString(), '\\') === self::PLUGIN_ANCESTOR
            || $class->extends->getLast() === 'CMSPlugin'
        ) {
            return true;
        }

        $className = $this->getName($class);

        if ($className === null || !$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        foreach ($this->reflectionProvider->getClass($className)->getParents() as $parentReflection) {
            if ($parentReflection->getName() === self::PLUGIN_ANCESTOR) {
                return true;
            }
        }

        return false;
    }
}
