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

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces $this->get('Items') calls in HtmlView classes with the equivalent model getter.
 *
 * Also inserts a /** @var ModelClass $model *\/ typehint comment above
 * $model = $this->getModel() when the class namespace follows the Joomla MVC
 * View\<Name>\HtmlView pattern. The model FQN is derived by replacing \View\
 * with \Model\, removing \HtmlView, and appending Model.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla5\HtmlViewGetToModelGetRector\HtmlViewGetToModelGetRectorTest
 */
final class HtmlViewGetToModelGetRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Replace \$this->get('Items') with \$model->getItems() in HtmlView classes, and add @var typehint",
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
namespace Acme\Component\Example\Site\View\Articles;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $items      = $this->get('Items');
        $pagination = $this->get('Pagination');
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
namespace Acme\Component\Example\Site\View\Articles;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        /** @var \Acme\Component\Example\Site\Model\ArticlesModel $model */
        $model      = $this->getModel();
        $items      = $model->getItems();
        $pagination = $model->getPagination();
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
        if ($node->name === null || $node->name->name !== 'HtmlView') {
            return null;
        }

        $modelFqn   = $this->deriveModelFqn($node);
        $hasChanged = false;

        foreach ($node->getMethods() as $classMethod) {
            if ($this->transformMethod($classMethod, $modelFqn)) {
                $hasChanged = true;
            }
        }

        return $hasChanged ? $node : null;
    }

    /**
     * Replace all $this->get('...') calls in a method with $model->get...() calls.
     * Adds a @var typehint comment and prepends $model = $this->getModel() if not already present.
     *
     * @return bool Whether the method was changed.
     */
    private function transformMethod(ClassMethod $classMethod, ?string $modelFqn): bool
    {
        if ($classMethod->stmts === null || $classMethod->stmts === []) {
            return false;
        }

        // First pass: detect whether any $this->get('...') calls exist
        $hasGetCall = false;

        $this->traverseNodesWithCallable($classMethod->stmts, function (Node $subNode) use (&$hasGetCall): ?Node {
            if ($this->isViewGetCall($subNode)) {
                $hasGetCall = true;
            }

            return null;
        });

        if (!$hasGetCall) {
            return false;
        }

        // Second pass: replace $this->get('Foo') with $model->getFoo()
        $this->traverseNodesWithCallable($classMethod->stmts, function (Node $subNode): ?Node {
            if (!$this->isViewGetCall($subNode)) {
                return null;
            }

            /** @var MethodCall $subNode */
            /** @var Arg $firstArg */
            $firstArg      = $subNode->args[0];
            /** @var String_ $stringNode */
            $stringNode    = $firstArg->value;
            $newMethodName = 'get' . ucfirst($stringNode->value);

            return new MethodCall(new Variable('model'), $newMethodName);
        });

        // Annotate or add $model = $this->getModel()
        $getModelStmt = $this->findGetModelStatement($classMethod);

        if ($getModelStmt !== null) {
            // Existing statement found — add comment if FQN derivable and not already set
            if ($modelFqn !== null && $getModelStmt->getDocComment() === null) {
                $getModelStmt->setDocComment(new Doc('/** @var \\' . $modelFqn . ' $model */'));
            }
        } elseif (!$this->isModelVariableDefined($classMethod)) {
            // No $model variable at all — prepend assignment with optional comment
            $newStmt = new Expression(
                new Assign(
                    new Variable('model'),
                    new MethodCall(new Variable('this'), 'getModel')
                )
            );

            if ($modelFqn !== null) {
                $newStmt->setDocComment(new Doc('/** @var \\' . $modelFqn . ' $model */'));
            }

            array_unshift($classMethod->stmts, $newStmt);
        }

        return true;
    }

    /**
     * Detect a $this->get('SomeString') call.
     */
    private function isViewGetCall(Node $node): bool
    {
        if (!$node instanceof MethodCall) {
            return false;
        }

        if (!$node->var instanceof Variable || !$this->isName($node->var, 'this')) {
            return false;
        }

        if (!$node->name instanceof Identifier || $node->name->name !== 'get') {
            return false;
        }

        if (\count($node->args) !== 1) {
            return false;
        }

        $arg = $node->args[0];

        return $arg instanceof Arg && $arg->value instanceof String_;
    }

    /**
     * Check whether $model is already assigned somewhere in the method body.
     */
    private function isModelVariableDefined(ClassMethod $classMethod): bool
    {
        if ($classMethod->stmts === null) {
            return false;
        }

        $isDefined = false;

        $this->traverseNodesWithCallable($classMethod->stmts, function (Node $node) use (&$isDefined): ?Node {
            if ($node instanceof Assign
                && $node->var instanceof Variable
                && $this->isName($node->var, 'model')) {
                $isDefined = true;
            }

            return null;
        });

        return $isDefined;
    }

    /**
     * Find a top-level $model = $this->getModel() statement in the method body.
     */
    private function findGetModelStatement(ClassMethod $classMethod): ?Expression
    {
        foreach ($classMethod->stmts ?? [] as $stmt) {
            if (!$stmt instanceof Expression || !$stmt->expr instanceof Assign) {
                continue;
            }

            $assign = $stmt->expr;

            if (!$assign->var instanceof Variable || !$this->isName($assign->var, 'model')) {
                continue;
            }

            if (!$assign->expr instanceof MethodCall) {
                continue;
            }

            $call = $assign->expr;

            if (!$call->var instanceof Variable || !$this->isName($call->var, 'this')) {
                continue;
            }

            if ($call->name instanceof Identifier && $call->name->name === 'getModel') {
                return $stmt;
            }
        }

        return null;
    }

    /**
     * Derive the model FQN from the HtmlView class FQN.
     *
     * Example: Acme\Site\View\Articles\HtmlView → Acme\Site\Model\ArticlesModel
     */
    private function deriveModelFqn(Class_ $class): ?string
    {
        $className = $this->getName($class);

        if ($className === null || !str_contains($className, '\\View\\')) {
            return null;
        }

        $modelFqn = str_replace('\\View\\', '\\Model\\', $className);

        if (!str_ends_with($modelFqn, '\\HtmlView')) {
            return null;
        }

        $base = substr($modelFqn, 0, -strlen('\\HtmlView'));

        return $base . 'Model';
    }
}
