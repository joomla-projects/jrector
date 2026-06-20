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
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces getDbo() calls with getDatabase() in DatabaseAwareTrait subclasses.
 *
 * Matched patterns:
 *   $this->getDbo()       → $this->getDatabase()
 *   Factory::getDbo()     → $this->getDatabase()
 *   JFactory::getDbo()    → $this->getDatabase()
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla5\GetDboToGetDatabaseRector\GetDboToGetDatabaseRectorTest
 */
final class GetDboToGetDatabaseRector extends AbstractRector
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
            'Replace getDbo() calls with getDatabase() in classes using the DatabaseAwareTrait',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class ExampleModel extends BaseDatabaseModel
{
    public function getItems(): array
    {
        $db = $this->getDbo();
        $db = Factory::getDbo();
        $db = JFactory::getDbo();

        return $db->loadObjectList();
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class ExampleModel extends BaseDatabaseModel
{
    public function getItems(): array
    {
        $db = $this->getDatabase();
        $db = $this->getDatabase();
        $db = $this->getDatabase();

        return $db->loadObjectList();
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
        if (!$this->hasDatabaseAwareTrait($node)) {
            return null;
        }

        $hasChanged = false;

        $this->traverseNodesWithCallable($node->stmts, function (Node $subNode) use (&$hasChanged): ?Node {
            if (!$this->isGetDboCall($subNode)) {
                return null;
            }

            $hasChanged = true;

            return new MethodCall(new Variable('this'), 'getDatabase');
        });

        return $hasChanged ? $node : null;
    }

    // -------------------------------------------------------------------------

    private function hasDatabaseAwareTrait(Class_ $class): bool
    {
        $className = $this->getName($class);
        if ($className === null || !$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        $classReflection = $this->reflectionProvider->getClass($className);

        foreach ($classReflection->getTraits(true) as $traitReflection) {
            if ($traitReflection->getName() === ltrim('Joomla\\Database\\DatabaseAwareTrait', '\\')) {
                return true;
            }
        }

        return false;
    }

    private function isGetDboCall(Node $node): bool
    {
        return $this->isThisGetDboCall($node) || $this->isStaticGetDboCall($node);
    }

    /**
     * Matches: $this->getDbo()
     */
    private function isThisGetDboCall(Node $node): bool
    {
        if (!$node instanceof MethodCall) {
            return false;
        }

        if (!$node->var instanceof Variable || !$this->isName($node->var, 'this')) {
            return false;
        }

        if (!$node->name instanceof Identifier || $node->name->name !== 'getDbo') {
            return false;
        }

        return \count($node->args) === 0;
    }

    /**
     * Matches: Factory::getDbo()  |  JFactory::getDbo()  |  \Joomla\CMS\Factory::getDbo()
     */
    private function isStaticGetDboCall(Node $node): bool
    {
        if (!$node instanceof StaticCall) {
            return false;
        }

        if (!$node->name instanceof Identifier || $node->name->name !== 'getDbo') {
            return false;
        }

        if (\count($node->args) !== 0) {
            return false;
        }

        if (!$node->class instanceof Name) {
            return false;
        }

        $callerName = ltrim($node->class->toString(), '\\');

        return $callerName == 'Joomla\CMS\Factory';
    }
}
