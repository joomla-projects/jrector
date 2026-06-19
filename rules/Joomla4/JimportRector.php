<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla4
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla4;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeVisitor;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Removes standalone jimport() calls whose argument starts with 'joomla.'.
 *
 * In Joomla 4 the autoloader makes all jimport('joomla.*') calls redundant.
 *
 * Matched pattern:
 *   jimport('joomla.<anything>');  →  (removed)
 *
 * Only standalone expression statements are removed. Calls embedded in
 * assignments or conditions are left untouched.
 *
 * @since  1.0.0
 * @see    \Rector\Tests\Naming\Rector\FuncCall\JoomlaJimportRector\JimportRectorTest
 */
final class JimportRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Remove jimport('joomla.*') calls that are no longer needed in Joomla 4",
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
jimport('joomla.application.component.view');
jimport('joomla.utilities.string');
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'

CODE_SAMPLE
                ),
            ]
        );
    }

    public function refactor(Node $node): Node|int|null
    {
        /** @var Expression $node */
        if (!$node->expr instanceof FuncCall) {
            return null;
        }

        $funcCall = $node->expr;

        if (!$this->isName($funcCall, 'jimport')) {
            return null;
        }

        if (\count($funcCall->args) === 0) {
            return null;
        }

        $firstArg = $funcCall->args[0];

        if (!$firstArg instanceof Arg) {
            return null;
        }

        if (!($firstArg->value instanceof String_)) {
            return null;
        }

        if (!str_starts_with($firstArg->value->value, 'joomla.')) {
            return null;
        }

        return NodeVisitor::REMOVE_NODE;
    }
}
