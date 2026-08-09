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
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Converts a legacy plugin handler with individual parameters to the event object signature.
 *
 * Joomla 6 calls event handlers with an event object. The old signature with individual (and
 * reference) parameters only worked through the legacy listener layer, which disappears together
 * with the legacy listeners.
 *
 * The body is left untouched: the original parameter names are restored as local variables at
 * the top of the method, so the diff stays reviewable and the rule stays small.
 *
 * The event class is written fully qualified. Enable Rector's `importNames()` — the example
 * configuration in assets/rector.php does — to turn it into a `use` statement.
 *
 * Shares its event knowledge with the other plugin event rules through JoomlaEventMap, so the
 * argument order and getter names are never duplicated.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla6\Plugin\LegacyHandlerSignatureRector\LegacyHandlerSignatureRectorTest
 */
final class LegacyHandlerSignatureRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * Configuration key for additional or overriding event definitions.
     */
    public const EVENT_ARGUMENT_MAP = 'event_argument_map';

    /**
     * Configuration key for additional event name => event class entries.
     */
    public const EVENT_NAME_MAP = 'event_name_map';

    private const PLUGIN_ANCESTOR = 'Joomla\\CMS\\Plugin\\CMSPlugin';

    /**
     * @var array<string, array{order: string[], getters: array<string, string>, resultAware: bool}>
     */
    private array $eventArgumentMap = JoomlaEventMap::DEFAULT_EVENT_ARGUMENT_MAP;

    /**
     * @var array<string, string>
     */
    private array $extraNameMap = [];

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

        $extraNameMap = $configuration[self::EVENT_NAME_MAP] ?? [];

        if (!\is_array($extraNameMap)) {
            return;
        }

        $normalised = [];

        foreach ($extraNameMap as $eventName => $className) {
            if (\is_string($eventName) && \is_string($className)) {
                $normalised[$eventName] = $className;
            }
        }

        $this->extraNameMap = $normalised;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert a legacy plugin handler signature to the typed event object signature',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class PlgContentExample extends CMSPlugin
{
    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        if ($context !== 'com_content.article') {
            return;
        }

        $article->text .= '<p>foo</p>';
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class PlgContentExample extends CMSPlugin
{
    public function onContentPrepare(\Joomla\CMS\Event\Content\ContentPrepareEvent $event): void
    {
        $context = $event->getContext();
        $article = $event->getItem();
        $params = $event->getParams();
        $page = $event->getPage();
        if ($context !== 'com_content.article') {
            return;
        }

        $article->text .= '<p>foo</p>';
    }
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

        $subscribedEvents = $this->collectSubscribedEvents($node);

        $hasChanged = false;

        foreach ($node->getMethods() as $method) {
            if ($this->refactorHandler($method, $subscribedEvents)) {
                $hasChanged = true;
            }
        }

        return $hasChanged ? $node : null;
    }

    // -------------------------------------------------------------------------

    /**
     * @param array<string, string> $subscribedEvents method name => event name
     */
    private function refactorHandler(ClassMethod $method, array $subscribedEvents): bool
    {
        if ($method->stmts === null || !$method->isPublic() || $method->isStatic()) {
            return false;
        }

        $methodName = $method->name->toString();

        // The event name comes from getSubscribedEvents() first, so handlers whose method name
        // differs from the event name are covered too.
        $eventName = $subscribedEvents[$methodName] ?? (str_starts_with($methodName, 'on') ? $methodName : null);

        if ($eventName === null) {
            return false;
        }

        $eventClass = $this->eventMap->resolveEventClassByName($eventName, $this->extraNameMap);

        if ($eventClass === null) {
            return false;
        }

        $definition = $this->eventMap->resolveDefinition($this->eventArgumentMap, $eventClass);

        if ($definition === null || $definition['order'] === []) {
            return false;
        }

        // Idempotence: the handler already takes an event object.
        if ($this->alreadyHasEventParameter($method)) {
            return false;
        }

        if ($method->params === []) {
            return false;
        }

        // More parameters than the event has known arguments — the lists do not match.
        if (\count($method->params) > \count($definition['order'])) {
            return false;
        }

        $assignments = [];

        foreach ($method->params as $position => $param) {
            if (!$param->var instanceof Variable || !\is_string($param->var->name) || $param->variadic) {
                return false;
            }

            // A reference parameter that is reassigned in the body writes its value back to the
            // caller. A getter cannot do that, so the handler is left for manual conversion.
            if ($param->byRef && $this->isVariableReassigned($method, $param->var->name)) {
                return false;
            }

            $argumentName = $definition['order'][$position];
            $getter       = $definition['getters'][$argumentName] ?? null;

            if ($getter === null) {
                return false;
            }

            $assignments[] = new Expression(
                new Assign(
                    new Variable($param->var->name),
                    new MethodCall(new Variable('event'), $getter)
                )
            );
        }

        $method->params = [
            new Param(new Variable('event'), null, new Name\FullyQualified($eventClass)),
        ];

        $method->stmts = array_merge($assignments, $method->stmts);

        // A handler that still returns a value is the job of HandlerReturnToEventResultRector.
        if ($method->returnType === null && !$this->returnsValue($method)) {
            $method->returnType = new Identifier('void');
        }

        $this->rewriteDocblock($method, $eventClass);

        return true;
    }

    private function alreadyHasEventParameter(ClassMethod $method): bool
    {
        foreach ($method->params as $param) {
            if (!$param->type instanceof Name) {
                continue;
            }

            $className = $this->getName($param->type);

            if ($className === null) {
                continue;
            }

            if (JoomlaEventMap::isGenericEventClass($className)) {
                return true;
            }

            if ($this->eventMap->resolveDefinition($this->eventArgumentMap, $className) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether the variable is assigned to anywhere in the method body, ignoring closures.
     */
    private function isVariableReassigned(ClassMethod $method, string $variableName): bool
    {
        $isReassigned = false;

        $this->traverseNodesWithCallable($method->stmts, static function (Node $node) use ($variableName, &$isReassigned): ?Node {
            if (!$node instanceof Assign) {
                return null;
            }

            // Only a direct reassignment loses the reference; $param->x = 1 is fine.
            if ($node->var instanceof Variable && $node->var->name === $variableName) {
                $isReassigned = true;
            }

            return null;
        });

        return $isReassigned;
    }

    private function returnsValue(ClassMethod $method): bool
    {
        $returnsValue = false;

        $this->traverseNodesWithCallable($method, static function (Node $node) use ($method, &$returnsValue) {
            // Returns inside closures belong to the closure.
            if ($node instanceof FunctionLike && $node !== $method) {
                return \PhpParser\NodeVisitor::DONT_TRAVERSE_CHILDREN;
            }

            if ($node instanceof Return_ && $node->expr !== null) {
                $returnsValue = true;
            }

            return null;
        });

        return $returnsValue;
    }

    /**
     * Replaces the `@param` lines of the old parameters with a single one for the event.
     */
    private function rewriteDocblock(ClassMethod $method, string $eventClass): void
    {
        $docComment = $method->getDocComment();

        if (!$docComment instanceof Doc) {
            return;
        }

        $text  = $docComment->getText();
        $lines = explode("\n", $text);

        $result       = [];
        $paramWritten = false;

        foreach ($lines as $line) {
            if (preg_match('/^\s*\*\s*@param\b/', $line) !== 1) {
                $result[] = $line;

                continue;
            }

            if (!$paramWritten) {
                $result[]     = '     * @param   \\' . $eventClass . '  $event  The event.';
                $paramWritten = true;
            }
        }

        $updated = implode("\n", $result);

        if ($updated !== $text) {
            $method->setDocComment(new Doc($updated));
        }
    }

    /**
     * Reads getSubscribedEvents() and returns `method name => event name`.
     *
     * @return array<string, string>
     */
    private function collectSubscribedEvents(Class_ $class): array
    {
        $method = $class->getMethod('getSubscribedEvents');

        if (!$method instanceof ClassMethod || $method->stmts === null) {
            return [];
        }

        $map = [];

        $this->traverseNodesWithCallable($method, static function (Node $node) use (&$map): ?Node {
            if (!$node instanceof ArrayItem || !$node->value instanceof String_ || !$node->key instanceof String_) {
                return null;
            }

            // ['onContentPrepare' => 'myHandler'] — the value is the method name.
            $map[$node->value->value] = $node->key->value;

            return null;
        });

        return $map;
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
