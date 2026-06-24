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
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces $var->input with $var->getInput() in method/function bodies where
 * $var was assigned from any of the following:
 *   - Factory::getApplication()
 *   - JFactory::getApplication()
 *   - \Joomla\CMS\Factory::getApplication()
 *   - $this->getApplication()
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla5\ApplicationInputPropertyRector\ApplicationInputPropertyRectorTest
 */
final class ApplicationInputPropertyRector extends AbstractRector
{
    private const GET_APPLICATION_CLASSES = ['Factory', 'JFactory', 'Joomla\\CMS\\Factory'];

    public function getNodeTypes(): array
    {
        return [ClassMethod::class, Function_::class];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace $app->input with $app->getInput() where $app comes from getApplication()',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class MyController extends BaseController
{
    public function execute(string $task): void
    {
        $app  = Factory::getApplication();
        $name = $app->input->get('name', '', 'string');
    }

    public function save(): void
    {
        $app  = $this->getApplication();
        $data = $app->input->getArray();
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class MyController extends BaseController
{
    public function execute(string $task): void
    {
        $app  = Factory::getApplication();
        $name = $app->getInput()->get('name', '', 'string');
    }

    public function save(): void
    {
        $app  = $this->getApplication();
        $data = $app->getInput()->getArray();
    }
}
CODE_SAMPLE
                ),
            ]
        );
    }

    public function refactor(Node $node): ?Node
    {
        /** @var ClassMethod|Function_ $node */
        if ($node->stmts === null || $node->stmts === []) {
            return null;
        }

        $appVarNames = $this->findGetApplicationVarNames($node->stmts);

        if ($appVarNames === []) {
            return null;
        }

        $hasChanged = false;

        $this->traverseNodesWithCallable($node->stmts, function (Node $subNode) use ($appVarNames, &$hasChanged): ?Node {
            if (!$subNode instanceof PropertyFetch) {
                return null;
            }

            if (!$subNode->var instanceof Variable) {
                return null;
            }

            $varName = $this->getName($subNode->var);

            if ($varName === null || !\in_array($varName, $appVarNames, true)) {
                return null;
            }

            if (!$subNode->name instanceof Identifier || $subNode->name->name !== 'input') {
                return null;
            }

            $hasChanged = true;

            return new MethodCall($subNode->var, 'getInput', []);
        });

        return $hasChanged ? $node : null;
    }

    /**
     * Collect variable names assigned from any getApplication() call in $stmts.
     *
     * @param  Node[]  $stmts
     * @return string[]
     */
    private function findGetApplicationVarNames(array $stmts): array
    {
        $assigns  = (new NodeFinder())->findInstanceOf($stmts, Assign::class);
        $varNames = [];

        foreach ($assigns as $assign) {
            if (!$assign->var instanceof Variable) {
                continue;
            }

            if (!$this->isGetApplicationCall($assign->expr)) {
                continue;
            }

            $varName = $this->getName($assign->var);

            if ($varName !== null) {
                $varNames[$varName] = true;
            }
        }

        return array_keys($varNames);
    }

    private function isGetApplicationCall(Node $node): bool
    {
        // Static call: Factory::getApplication(), JFactory::getApplication(), \Joomla\CMS\Factory::getApplication()
        if ($node instanceof StaticCall) {
            if (!$node->name instanceof Identifier || $node->name->name !== 'getApplication') {
                return false;
            }

            if (!$node->class instanceof Name) {
                return false;
            }

            return \in_array($node->class->toString(), self::GET_APPLICATION_CLASSES, true);
        }

        // Instance call: $this->getApplication()
        if ($node instanceof MethodCall) {
            if (!$node->var instanceof Variable || !$this->isName($node->var, 'this')) {
                return false;
            }

            return $node->name instanceof Identifier && $node->name->name === 'getApplication';
        }

        return false;
    }
}
