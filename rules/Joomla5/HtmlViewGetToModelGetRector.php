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
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
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
 * A call is only converted when the derived model class can be resolved and
 * the corresponding getter (e.g. getItems()) exists on it, mirroring the
 * method_exists() check in the legacy AbstractView::get().
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla5\HtmlViewGetToModelGetRector\HtmlViewGetToModelGetRectorTest
 */
final class HtmlViewGetToModelGetRector extends AbstractRector
{
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

        $modelFqn = $this->deriveModelFqn($node);

        // Without a resolvable model class we cannot verify that the getters exist
        if ($modelFqn === null || !$this->reflectionProvider->hasClass($modelFqn)) {
            return null;
        }

        $modelReflection = $this->reflectionProvider->getClass($modelFqn);
        $hasChanged      = false;

        foreach ($node->getMethods() as $classMethod) {
            if ($this->transformMethod($classMethod, $modelReflection)) {
                $hasChanged = true;
            }
        }

        return $hasChanged ? $node : null;
    }

    /**
     * Replace all $this->get('...') calls in a method with $model->get...() calls,
     * as long as the matching getter exists on the model.
     * Adds a @var typehint comment and prepends $model = $this->getModel() if not already present.
     * Moves an existing $model = $this->getModel() to before the first $model usage if necessary.
     *
     * @return bool Whether the method was changed.
     */
    private function transformMethod(ClassMethod $classMethod, ClassReflection $modelReflection): bool
    {
        if ($classMethod->stmts === null || $classMethod->stmts === []) {
            return false;
        }

        $modelFqn = $modelReflection->getName();

        // First pass: detect whether any convertible $this->get('...') calls exist
        $hasGetCall = false;

        $this->traverseNodesWithCallable($classMethod->stmts, function (Node $subNode) use (&$hasGetCall, $modelReflection): ?Node {
            if ($this->getModelGetterName($subNode, $modelReflection) !== null) {
                $hasGetCall = true;
            }

            return null;
        });

        if (!$hasGetCall) {
            return false;
        }

        // Second pass: replace $this->get('Foo') with $model->getFoo()
        $this->traverseNodesWithCallable($classMethod->stmts, function (Node $subNode) use ($modelReflection): ?Node {
            $newMethodName = $this->getModelGetterName($subNode, $modelReflection);

            if ($newMethodName === null) {
                return null;
            }

            return new MethodCall(new Variable('model'), $newMethodName);
        });

        // Locate existing $model = $this->getModel() statement
        $getModelResult = $this->findGetModelStatementWithIndex($classMethod);

        if ($getModelResult !== null) {
            [$getModelStmt, $getModelIdx] = $getModelResult;

            // After the $this->get() → $model->get() replacement the assignment may now
            // sit below the first statement that reads $model. Move it up if needed.
            $firstUsageIdx = $this->findFirstModelVariableUsageIndex($classMethod->stmts, $getModelIdx);

            if ($firstUsageIdx < $getModelIdx) {
                array_splice($classMethod->stmts, $getModelIdx, 1);
                array_splice($classMethod->stmts, $firstUsageIdx, 0, [$getModelStmt]);
            }

            // Add @var typehint if FQN is known and not already annotated
            if ($getModelStmt->getDocComment() === null) {
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

            $newStmt->setDocComment(new Doc('/** @var \\' . $modelFqn . ' $model */'));

            array_unshift($classMethod->stmts, $newStmt);
        }

        return true;
    }

    /**
     * Return the model getter name (e.g. getItems) for a $this->get('Items') call,
     * or null if the node is no such call or the getter does not exist on the model.
     */
    private function getModelGetterName(Node $node, ClassReflection $modelReflection): ?string
    {
        if (!$this->isViewGetCall($node)) {
            return null;
        }

        /** @var MethodCall $node */
        /** @var Arg $firstArg */
        $firstArg   = $node->args[0];
        /** @var String_ $stringNode */
        $stringNode = $firstArg->value;
        $methodName = 'get' . ucfirst($stringNode->value);

        // Native check, matching the method_exists() test in AbstractView::get()
        return $modelReflection->hasNativeMethod($methodName) ? $methodName : null;
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
     * Returns [Expression $stmt, int $index] or null.
     *
     * @return array{0: Expression, 1: int}|null
     */
    private function findGetModelStatementWithIndex(ClassMethod $classMethod): ?array
    {
        foreach ($classMethod->stmts ?? [] as $idx => $stmt) {
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
                return [$stmt, $idx];
            }
        }

        return null;
    }

    /**
     * Find the index of the first top-level statement that reads the $model variable.
     * The statement at $excludeIdx (the $model = $this->getModel() assignment) is skipped.
     * Returns PHP_INT_MAX when $model is not read in any other statement.
     */
    private function findFirstModelVariableUsageIndex(array $stmts, int $excludeIdx): int
    {
        foreach ($stmts as $idx => $stmt) {
            if ($idx === $excludeIdx) {
                continue;
            }

            $hasUsage = false;

            $this->traverseNodesWithCallable([$stmt], function (Node $node) use (&$hasUsage): ?Node {
                if ($node instanceof Variable && $this->isName($node, 'model')) {
                    $hasUsage = true;
                }

                return null;
            });

            if ($hasUsage) {
                return $idx;
            }
        }

        return PHP_INT_MAX;
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

        $base = substr($modelFqn, 0, -\strlen('\\HtmlView'));

        return $base . 'Model';
    }
}
