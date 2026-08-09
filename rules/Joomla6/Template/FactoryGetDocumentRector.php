<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla6
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla6\Template;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitor;
use PHPStan\Reflection\ReflectionProvider;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces `Factory::getDocument()` with the getter that fits the context.
 *
 * The static access bypasses the DI container and makes code untestable. Depending on the
 * context a suitable getter already exists, in the same spirit as GetDboToGetDatabaseRector and
 * CurrentUserInterfaceGetUserRector.
 *
 * The order of the checks is binding, first match wins:
 *
 *   1. the class implements `Joomla\CMS\Document\DocumentAwareInterface` => `$this->getDocument()`
 *   2. the class has a `getApplication()` => `$this->getApplication()->getDocument()`
 *   3. a variable in scope holds `Factory::getApplication()` => `$app->getDocument()`
 *   4. otherwise the call is left alone
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla6\Template\FactoryGetDocumentRector\FactoryGetDocumentRectorTest
 */
final class FactoryGetDocumentRector extends AbstractRector
{
    private const DOCUMENT_AWARE_INTERFACE = 'Joomla\\CMS\\Document\\DocumentAwareInterface';

    private const PLUGIN_ANCESTOR = 'Joomla\\CMS\\Plugin\\CMSPlugin';

    /**
     * Short names accepted for the Factory, so JFactory and the FQN form are covered too.
     *
     * @var string[]
     */
    private const FACTORY_NAMES = ['Factory', 'JFactory'];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace Factory::getDocument() with the getter that fits the context',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class ExampleView implements DocumentAwareInterface
{
    public function display($tpl = null)
    {
        $doc = Factory::getDocument();
        $doc->setTitle('Example');
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class ExampleView implements DocumentAwareInterface
{
    public function display($tpl = null)
    {
        $doc = $this->getDocument();
        $doc->setTitle('Example');
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
        return [Class_::class, FileNode::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Class_) {
            return $this->refactorClass($node);
        }

        /** @var FileNode $node */
        return $this->refactorFileScope($node);
    }

    // -------------------------------------------------------------------------

    private function refactorClass(Class_ $class): ?Class_
    {
        if ($class->isAnonymous()) {
            return null;
        }

        // Checks 1 and 2 apply to the whole class.
        $classLevelReplacement = null;

        if ($this->implementsDocumentAware($class)) {
            $classLevelReplacement = new MethodCall(new Variable('this'), 'getDocument');
        } elseif ($this->hasApplicationGetter($class)) {
            $classLevelReplacement = new MethodCall(
                new MethodCall(new Variable('this'), 'getApplication'),
                'getDocument'
            );
        }

        $hasChanged = false;

        foreach ($class->getMethods() as $method) {
            if ($method->stmts === null) {
                continue;
            }

            // Check 3 is scoped to the method the variable was assigned in.
            $replacement = $classLevelReplacement ?? $this->buildApplicationVariableReplacement($method);

            if ($replacement === null) {
                continue;
            }

            if ($this->replaceFactoryCalls($method, $replacement)) {
                $hasChanged = true;
            }
        }

        return $hasChanged ? $class : null;
    }

    /**
     * Handles calls outside any class, e.g. directly in a template file. Only check 3 can apply
     * there, because there is no `$this`.
     */
    private function refactorFileScope(FileNode $fileNode): ?FileNode
    {
        $applicationVariable = null;

        // Collect only from the file scope; class bodies are handled by refactorClass().
        $this->traverseNodesWithCallable($fileNode->stmts, function (Node $node) use (&$applicationVariable) {
            if ($node instanceof Class_) {
                return NodeVisitor::DONT_TRAVERSE_CHILDREN;
            }

            if ($applicationVariable !== null) {
                return null;
            }

            $applicationVariable = $this->matchApplicationAssignment($node);

            return null;
        });

        if ($applicationVariable === null) {
            return null;
        }

        $replacement = new MethodCall(new Variable($applicationVariable), 'getDocument');
        $hasChanged  = false;

        $this->traverseNodesWithCallable($fileNode->stmts, function (Node $node) use ($replacement, &$hasChanged) {
            if ($node instanceof Class_) {
                return NodeVisitor::DONT_TRAVERSE_CHILDREN;
            }

            if (!$this->isFactoryGetDocumentCall($node)) {
                return null;
            }

            $hasChanged = true;

            return clone $replacement;
        });

        return $hasChanged ? $fileNode : null;
    }

    private function replaceFactoryCalls(ClassMethod $method, Expr $replacement): bool
    {
        $hasChanged = false;

        $this->traverseNodesWithCallable($method, function (Node $node) use ($replacement, &$hasChanged) {
            if (!$this->isFactoryGetDocumentCall($node)) {
                return null;
            }

            $hasChanged = true;

            // Cloned, so every call site gets its own node.
            return clone $replacement;
        });

        return $hasChanged;
    }

    private function isFactoryGetDocumentCall(Node $node): bool
    {
        if (!$node instanceof StaticCall || !$node->class instanceof Node\Name) {
            return false;
        }

        if (!$this->isName($node->name, 'getDocument')) {
            return false;
        }

        // A call with arguments is something else and is left alone.
        if ($node->args !== []) {
            return false;
        }

        $className = ltrim($node->class->toString(), '\\');

        return \in_array($node->class->getLast(), self::FACTORY_NAMES, true)
            || $className === 'Joomla\\CMS\\Factory';
    }

    /**
     * Returns the name of the first variable assigned from Factory::getApplication() in the method.
     */
    private function buildApplicationVariableReplacement(ClassMethod $method): ?MethodCall
    {
        $variableName = null;

        $this->traverseNodesWithCallable($method, function (Node $node) use (&$variableName): ?Node {
            if ($variableName === null) {
                $variableName = $this->matchApplicationAssignment($node);
            }

            return null;
        });

        return $variableName === null ? null : new MethodCall(new Variable($variableName), 'getDocument');
    }

    /**
     * Matches `$x = Factory::getApplication();` and returns `x`.
     */
    private function matchApplicationAssignment(Node $node): ?string
    {
        if (!$node instanceof Assign || !$node->var instanceof Variable || !\is_string($node->var->name)) {
            return null;
        }

        if (!$node->expr instanceof StaticCall || !$node->expr->class instanceof Node\Name) {
            return null;
        }

        if (!$this->isName($node->expr->name, 'getApplication')) {
            return null;
        }

        $className = ltrim($node->expr->class->toString(), '\\');

        if (!\in_array($node->expr->class->getLast(), self::FACTORY_NAMES, true) && $className !== 'Joomla\\CMS\\Factory') {
            return null;
        }

        return $node->var->name;
    }

    // -------------------------------------------------------------------------
    // Context detection
    // -------------------------------------------------------------------------

    private function implementsDocumentAware(Class_ $class): bool
    {
        foreach ($class->implements as $interface) {
            if (
                ltrim($interface->toString(), '\\') === self::DOCUMENT_AWARE_INTERFACE
                || $interface->getLast() === 'DocumentAwareInterface'
            ) {
                return true;
            }
        }

        $className = $this->getName($class);

        if ($className === null || !$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        return $this->reflectionProvider->getClass($className)->implementsInterface(self::DOCUMENT_AWARE_INTERFACE);
    }

    /**
     * The class must be shown to have getApplication() — an own method, a CMSPlugin ancestry or
     * anything reflection can confirm. Guessing here would produce calls to a missing method.
     */
    private function hasApplicationGetter(Class_ $class): bool
    {
        if ($class->getMethod('getApplication') instanceof ClassMethod) {
            return true;
        }

        if (
            $class->extends !== null
            && (
                ltrim($class->extends->toString(), '\\') === self::PLUGIN_ANCESTOR
                || $class->extends->getLast() === 'CMSPlugin'
            )
        ) {
            return true;
        }

        $className = $this->getName($class);

        if ($className === null || !$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        return $this->reflectionProvider->getClass($className)->hasMethod('getApplication');
    }
}
