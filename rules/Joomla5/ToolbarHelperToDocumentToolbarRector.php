<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla5
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla5;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces ToolbarHelper::<method>() static calls with $toolbar-><method>() instance calls
 * in classes that implement \Joomla\CMS\Document\DocumentAwareInterface.
 *
 * Inserts $toolbar = $this->getDocument()->getToolbar() once per method, immediately
 * before the first qualifying ToolbarHelper call. ToolbarHelper::title() is excluded.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla5\ToolbarHelperToDocumentToolbarRector\ToolbarHelperToDocumentToolbarRectorTest
 */
final class ToolbarHelperToDocumentToolbarRector extends AbstractRector
{
    private const TARGET_INTERFACE     = 'Joomla\CMS\Document\DocumentAwareInterface';
    private const TOOLBAR_HELPER_SHORT = 'ToolbarHelper';
    private const TOOLBAR_HELPER_FQN   = 'Joomla\CMS\Toolbar\ToolbarHelper';

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace ToolbarHelper::x() static calls with $toolbar->x() in classes implementing DocumentAwareInterface',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Joomla\CMS\Document\DocumentAwareInterface;

class ExampleView implements DocumentAwareInterface
{
    public function addToolbar(): void
    {
        ToolbarHelper::addNew('article.add');
        ToolbarHelper::editList('article.edit');
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Joomla\CMS\Document\DocumentAwareInterface;

class ExampleView implements DocumentAwareInterface
{
    public function addToolbar(): void
    {
        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->addNew('article.add');
        $toolbar->editList('article.edit');
    }
}
CODE_SAMPLE
                ),
            ]
        );
    }

    public function refactor(Node $node): ?Node
    {
        /** @var Class_ $node */
        if (!$this->implementsDocumentAwareInterface($node)) {
            return null;
        }

        $hasChanged = false;

        foreach ($node->getMethods() as $classMethod) {
            if ($this->transformMethod($classMethod)) {
                $hasChanged = true;
            }
        }

        return $hasChanged ? $node : null;
    }

    private function transformMethod(ClassMethod $classMethod): bool
    {
        if ($classMethod->stmts === null || $classMethod->stmts === []) {
            return false;
        }

        // Detect if any ToolbarHelper::x() calls exist (excluding title())
        $hasToolbarCall = false;
        $this->traverseNodesWithCallable($classMethod->stmts, function (Node $subNode) use (&$hasToolbarCall): ?Node {
            if ($this->isToolbarHelperCall($subNode)) {
                $hasToolbarCall = true;
            }

            return null;
        });

        if (!$hasToolbarCall) {
            return false;
        }

        // Find first top-level statement index that contains a ToolbarHelper call (before replacement)
        $firstIdx = $this->findFirstToolbarCallStatementIndex($classMethod->stmts);

        // Check if $toolbar is already assigned anywhere in this method
        $toolbarAlreadyDefined = $this->isToolbarVariableDefined($classMethod);

        // Replace ToolbarHelper::x(args) → $toolbar->x(args)
        $this->traverseNodesWithCallable($classMethod->stmts, function (Node $subNode): ?Node {
            if (!$this->isToolbarHelperCall($subNode)) {
                return null;
            }

            /** @var StaticCall $subNode */
            return new MethodCall(new Variable('toolbar'), $subNode->name, $subNode->args);
        });

        // Insert $toolbar = $this->getDocument()->getToolbar() before the first replaced call
        if (!$toolbarAlreadyDefined && $firstIdx !== null) {
            $toolbarAssignment = new Expression(
                new Assign(
                    new Variable('toolbar'),
                    new MethodCall(
                        new MethodCall(new Variable('this'), 'getDocument'),
                        'getToolbar'
                    )
                )
            );
            array_splice($classMethod->stmts, $firstIdx, 0, [$toolbarAssignment]);
        }

        return true;
    }

    private function implementsDocumentAwareInterface(Class_ $class): bool
    {
        // Direct: check implements list in AST (no reflection needed)
        foreach ($class->implements as $implement) {
            if (ltrim($implement->toString(), '\\') === self::TARGET_INTERFACE) {
                return true;
            }
        }

        // Indirect: walk the full interface chain via PHPStan reflection
        $className = $this->getName($class);

        if ($className === null) {
            return false;
        }

        if (!$this->reflectionProvider->hasClass($className)
            || !$this->reflectionProvider->hasClass(self::TARGET_INTERFACE)) {
            return false;
        }

        return $this->reflectionProvider->getClass($className)->implementsInterface(self::TARGET_INTERFACE);
    }

    /**
     * Returns true for ToolbarHelper::<method>() static calls where <method> is not 'title'.
     */
    private function isToolbarHelperCall(Node $node): bool
    {
        if (!$node instanceof StaticCall) {
            return false;
        }

        if (!$node->class instanceof Name) {
            return false;
        }

        $className = ltrim($node->class->toString(), '\\');

        if ($className !== self::TOOLBAR_HELPER_SHORT && $className !== self::TOOLBAR_HELPER_FQN) {
            return false;
        }

        if (!$node->name instanceof Identifier) {
            return false;
        }

        return $node->name->name !== 'title';
    }

    /**
     * Find the index of the first top-level statement that contains a ToolbarHelper call.
     */
    private function findFirstToolbarCallStatementIndex(array $stmts): ?int
    {
        foreach ($stmts as $idx => $stmt) {
            $found = false;

            $this->traverseNodesWithCallable([$stmt], function (Node $subNode) use (&$found): ?Node {
                if ($this->isToolbarHelperCall($subNode)) {
                    $found = true;
                }

                return null;
            });

            if ($found) {
                return $idx;
            }
        }

        return null;
    }

    /**
     * Check whether $toolbar is already assigned anywhere in the method body.
     */
    private function isToolbarVariableDefined(ClassMethod $classMethod): bool
    {
        $isDefined = false;

        $this->traverseNodesWithCallable($classMethod->stmts ?? [], function (Node $subNode) use (&$isDefined): ?Node {
            if ($subNode instanceof Assign
                && $subNode->var instanceof Variable
                && $this->isName($subNode->var, 'toolbar')) {
                $isDefined = true;
            }

            return null;
        });

        return $isDefined;
    }
}
