<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla6
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla6\Module;

use PhpParser\Comment;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Reflection\ReflectionProvider;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Moves the logic of a hand written module `dispatch()` into `getLayoutData()`.
 *
 * After a plain structural conversion the whole legacy module body usually still sits in
 * `dispatch()`, including `require ModuleHelper::getLayoutPath(...)`. The intended split is that
 * `getLayoutData()` returns the data and `AbstractModuleDispatcher::dispatch()` renders the
 * layout — only then do layout overrides and module caching work reliably.
 *
 * The rule is deliberately conservative about local variables: it cannot know which of them the
 * layout expects, so it does not rewrite them into `$data`. It marks the method with a TODO
 * instead. Silently guessing would produce modules that render blank in ways that are hard to
 * trace back.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla6\Module\DispatcherGetLayoutDataRector\DispatcherGetLayoutDataRectorTest
 */
final class DispatcherGetLayoutDataRector extends AbstractRector
{
    private const DISPATCHER_ANCESTOR = 'Joomla\\CMS\\Dispatcher\\AbstractModuleDispatcher';

    private const TODO_COMMENT = '// TODO jrector: move every local variable the layout uses into $data, e.g. $data[\'items\'] = $items;';

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert a hand written module dispatch() method into getLayoutData()',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class Dispatcher extends AbstractModuleDispatcher
{
    public function dispatch()
    {
        $params = $this->module->params;
        $items  = $this->getItems($params);

        require ModuleHelper::getLayoutPath('mod_foo', $params->get('layout', 'default'));
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();
        // TODO jrector: move every local variable the layout uses into $data, e.g. $data['items'] = $items;
        $params = $this->module->params;
        $items  = $this->getItems($params);
        return $data;
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
        if ($node->isAnonymous() || !$this->isModuleDispatcher($node)) {
            return null;
        }

        // Idempotence: a class that already provides getLayoutData() is done.
        if ($node->getMethod('getLayoutData') instanceof ClassMethod) {
            return null;
        }

        $dispatch = $node->getMethod('dispatch');

        if (!$dispatch instanceof ClassMethod || $dispatch->stmts === null) {
            return null;
        }

        $layoutIncludeKey = $this->findLayoutIncludeKey($dispatch->stmts);

        // Without a getLayoutPath() include the method does something else — leave it alone.
        if ($layoutIncludeKey === null) {
            return null;
        }

        unset($dispatch->stmts[$layoutIncludeKey]);
        $dispatch->stmts = array_values($dispatch->stmts);

        $dispatch->name       = new Identifier('getLayoutData');
        $dispatch->flags      = ($dispatch->flags & ~Modifiers::PUBLIC & ~Modifiers::PRIVATE) | Modifiers::PROTECTED;
        $dispatch->returnType = new Identifier('array');

        $parentCall = new Expression(
            new Assign(new Variable('data'), new StaticCall(new Node\Name('parent'), 'getLayoutData'))
        );

        if ($this->hasLocalVariableAssignment($dispatch->stmts)) {
            $parentCall->setAttribute(AttributeKey::COMMENTS, [new Comment(self::TODO_COMMENT)]);
        }

        $dispatch->stmts = array_merge(
            [$parentCall],
            $dispatch->stmts,
            [new Return_(new Variable('data'))]
        );

        return $node;
    }

    // -------------------------------------------------------------------------

    /**
     * Returns the key of the `require`/`include` statement that renders the layout.
     *
     * @param Node\Stmt[] $stmts
     */
    private function findLayoutIncludeKey(array $stmts): ?int
    {
        foreach ($stmts as $key => $stmt) {
            if (!$stmt instanceof Expression || !$stmt->expr instanceof Include_) {
                continue;
            }

            $included = $stmt->expr->expr;

            if (!$included instanceof StaticCall) {
                continue;
            }

            if (!$this->isName($included->name, 'getLayoutPath')) {
                continue;
            }

            // Both the imported short name and the fully qualified name are accepted.
            $className = $included->class instanceof Node\Name ? $included->class->getLast() : null;

            if ($className !== 'ModuleHelper') {
                continue;
            }

            return $key;
        }

        return null;
    }

    /**
     * Tells whether the body assigns to any local variable, i.e. whether data may be expected
     * by the layout that the rule cannot move into $data on its own.
     *
     * @param Node\Stmt[] $stmts
     */
    private function hasLocalVariableAssignment(array $stmts): bool
    {
        $hasAssignment = false;

        $this->traverseNodesWithCallable($stmts, static function (Node $node) use (&$hasAssignment): ?Node {
            if ($node instanceof Assign && $node->var instanceof Variable) {
                $hasAssignment = true;
            }

            return null;
        });

        return $hasAssignment;
    }

    private function isModuleDispatcher(Class_ $class): bool
    {
        if ($class->extends === null) {
            return false;
        }

        if (
            ltrim($class->extends->toString(), '\\') === self::DISPATCHER_ANCESTOR
            || $class->extends->getLast() === 'AbstractModuleDispatcher'
        ) {
            return true;
        }

        $className = $this->getName($class);

        if ($className === null || !$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        foreach ($this->reflectionProvider->getClass($className)->getParents() as $parentReflection) {
            if ($parentReflection->getName() === self::DISPATCHER_ANCESTOR) {
                return true;
            }
        }

        return false;
    }
}
