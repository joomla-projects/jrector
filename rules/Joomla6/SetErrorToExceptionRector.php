<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla6
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla6;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces the legacy Joomla error-return pattern with a proper exception throw.
 *
 * Before:
 *   $this->setError('Some error');
 *   return false;
 *
 * After:
 *   throw new \Exception('Some error');
 *
 * The rule handles arbitrary expressions as the setError argument (strings,
 * variables, method calls, …) and recurses into nested blocks (if/else,
 * foreach, for, while, try/catch).
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla6\SetErrorToExceptionRector\SetErrorToExceptionRectorTest
 */
final class SetErrorToExceptionRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Replace \$this->setError('msg') followed by return false with throw new \\Exception('msg')",
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class ExampleModel extends BaseDatabaseModel
{
    public function save($data)
    {
        if (!$this->validate($data)) {
            $this->setError('Validation failed');
            return false;
        }
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class ExampleModel extends BaseDatabaseModel
{
    public function save($data)
    {
        if (!$this->validate($data)) {
            throw new \Exception('Validation failed');
        }
    }
}
CODE_SAMPLE
                ),
            ]
        );
    }

    public function refactor(Node $node): ?Node
    {
        /** @var ClassMethod $node */
        if ($node->stmts === null || $node->stmts === []) {
            return null;
        }

        $newStmts = $this->transformStmts($node->stmts);

        if ($newStmts === null) {
            return null;
        }

        $node->stmts = $newStmts;

        return $node;
    }

    // -------------------------------------------------------------------------

    /**
     * Scan a flat list of statements for the setError + return false pair.
     * Recurses into nested statement blocks (if, foreach, …).
     *
     * @param  Node\Stmt[]       $stmts
     * @return Node\Stmt[]|null  Modified list, or null when nothing changed.
     */
    private function transformStmts(array $stmts): ?array
    {
        $hasChanged = false;
        $result     = [];
        $count      = \count($stmts);
        $i          = 0;

        while ($i < $count) {
            $stmt = $stmts[$i];
            $next = ($i + 1 < $count) ? $stmts[$i + 1] : null;

            if ($next !== null && $this->isSetError($stmt) && $this->isReturnFalse($next)) {
                /** @var Expression $stmt */
                $result[] = $this->buildThrow($stmt);
                $i += 2;
                $hasChanged = true;
                continue;
            }

            $modified = $this->processNestedStmt($stmt);

            if ($modified !== null) {
                $result[]   = $modified;
                $hasChanged = true;
            } else {
                $result[] = $stmt;
            }

            $i++;
        }

        return $hasChanged ? $result : null;
    }

    /**
     * Recurse into blocks that can contain further statements.
     *
     * @return Node\Stmt|null  The (possibly mutated) node, or null if unchanged.
     */
    private function processNestedStmt(Node\Stmt $stmt): ?Node\Stmt
    {
        $changed = false;

        if ($stmt instanceof If_) {
            $newStmts = $this->transformStmts($stmt->stmts);

            if ($newStmts !== null) {
                $stmt->stmts = $newStmts;
                $changed     = true;
            }

            foreach ($stmt->elseifs as $elseif) {
                $newStmts = $this->transformStmts($elseif->stmts);

                if ($newStmts !== null) {
                    $elseif->stmts = $newStmts;
                    $changed       = true;
                }
            }

            if ($stmt->else instanceof Else_) {
                $newStmts = $this->transformStmts($stmt->else->stmts);

                if ($newStmts !== null) {
                    $stmt->else->stmts = $newStmts;
                    $changed           = true;
                }
            }
        } elseif ($stmt instanceof Foreach_ || $stmt instanceof For_ || $stmt instanceof While_) {
            $newStmts = $this->transformStmts($stmt->stmts);

            if ($newStmts !== null) {
                $stmt->stmts = $newStmts;
                $changed     = true;
            }
        } elseif ($stmt instanceof TryCatch) {
            $newStmts = $this->transformStmts($stmt->stmts);

            if ($newStmts !== null) {
                $stmt->stmts = $newStmts;
                $changed     = true;
            }

            foreach ($stmt->catches as $catch) {
                $newStmts = $this->transformStmts($catch->stmts);

                if ($newStmts !== null) {
                    $catch->stmts = $newStmts;
                    $changed      = true;
                }
            }

            if ($stmt->finally !== null) {
                $newStmts = $this->transformStmts($stmt->finally->stmts);

                if ($newStmts !== null) {
                    $stmt->finally->stmts = $newStmts;
                    $changed              = true;
                }
            }
        }

        return $changed ? $stmt : null;
    }

    // -------------------------------------------------------------------------
    // Node pattern matchers
    // -------------------------------------------------------------------------

    /**
     * Matches: $this->setError(<expr>)  as a standalone statement.
     */
    private function isSetError(Node\Stmt $stmt): bool
    {
        if (!$stmt instanceof Expression || !$stmt->expr instanceof MethodCall) {
            return false;
        }

        $call = $stmt->expr;

        if (!$call->var instanceof Variable || !$this->isName($call->var, 'this')) {
            return false;
        }

        if (!$call->name instanceof Identifier || $call->name->name !== 'setError') {
            return false;
        }

        return \count($call->args) >= 1;
    }

    /**
     * Matches: return false;
     */
    private function isReturnFalse(Node\Stmt $stmt): bool
    {
        if (!$stmt instanceof Return_ || !$stmt->expr instanceof ConstFetch) {
            return false;
        }

        return strtolower($stmt->expr->name->toString()) === 'false';
    }

    // -------------------------------------------------------------------------
    // Node builder
    // -------------------------------------------------------------------------

    /**
     * Build:  throw new \Exception(<original_message_expr>);
     */
    private function buildThrow(Expression $setErrorStmt): Expression
    {
        /** @var MethodCall $call */
        $call = $setErrorStmt->expr;

        $args      = [];
        $firstArg  = $call->args[0] ?? null;

        if ($firstArg instanceof Arg) {
            $args[] = new Arg($firstArg->value);
        }

        return new Expression(
            new Throw_(
                new New_(new Name\FullyQualified('Exception'), $args)
            )
        );
    }
}
