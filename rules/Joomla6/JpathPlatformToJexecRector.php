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

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Switches the direct access guard from JPATH_PLATFORM to _JEXEC.
 *
 * Verified against Joomla 6.1.1: `JPATH_PLATFORM` does not occur once in `libraries/`, so the
 * constant is only available while the backward compatibility plugin is active. A file guarded
 * with it then aborts immediately — and because `die` produces no message, the symptom is a blank
 * page that is unpleasant to trace. `_JEXEC` is the intended check.
 *
 * Only the string argument is touched. A leading backslash, the `or` / `||` form and the shape of
 * `die` are all preserved.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla6\JpathPlatformToJexecRector\JpathPlatformToJexecRectorTest
 */
final class JpathPlatformToJexecRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * Configuration key: also mark other JPATH_PLATFORM usages with a TODO comment.
     */
    public const MARK_OTHER_USAGES = 'mark_other_usages';

    private const OLD_CONSTANT = 'JPATH_PLATFORM';

    private const NEW_CONSTANT = '_JEXEC';

    private const TODO_COMMENT = '// TODO jrector: JPATH_PLATFORM is no longer defined in Joomla 6';

    private bool $markOtherUsages = false;

    /**
     * @param array<string, mixed> $configuration
     */
    public function configure(array $configuration): void
    {
        $this->markOtherUsages = (bool) ($configuration[self::MARK_OTHER_USAGES] ?? false);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace the JPATH_PLATFORM direct access guard with _JEXEC',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
\defined('JPATH_PLATFORM') or die;
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
\defined('_JEXEC') or die;
CODE_SAMPLE,
                    [self::MARK_OTHER_USAGES => false]
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Expression::class, If_::class];
    }

    /**
     * @param Expression|If_ $node
     */
    public function refactor(Node $node): ?Node
    {
        $definedCalls = $this->collectDefinedCalls($node);

        // A statement checking both constants cannot be rewritten mechanically — the author
        // meant something specific by it. Leave it and point at it instead.
        if ($this->checksBothConstants($definedCalls)) {
            return $this->markStatement($node);
        }

        $hasChanged = false;

        foreach ($definedCalls as $string) {
            if ($string->value !== self::OLD_CONSTANT) {
                continue;
            }

            $string->value = self::NEW_CONSTANT;
            $hasChanged    = true;
        }

        if ($hasChanged) {
            return $node;
        }

        // Any remaining JPATH_PLATFORM in this statement is a value usage, e.g. a path.
        if ($this->markOtherUsages && $this->usesConstantAsValue($node)) {
            return $this->markStatement($node);
        }

        return null;
    }

    // -------------------------------------------------------------------------

    /**
     * Collects the string literals of every `defined('...')` call in the statement.
     *
     * @return String_[]
     */
    private function collectDefinedCalls(Stmt $stmt): array
    {
        $strings = [];

        $this->traverseNodesWithCallable($stmt, function (Node $node) use (&$strings): ?Node {
            if (!$node instanceof FuncCall || !$this->isName($node, 'defined')) {
                return null;
            }

            $firstArg = $node->args[0] ?? null;

            if ($firstArg instanceof Node\Arg && $firstArg->value instanceof String_) {
                $strings[] = $firstArg->value;
            }

            return null;
        });

        return $strings;
    }

    /**
     * @param String_[] $definedCalls
     */
    private function checksBothConstants(array $definedCalls): bool
    {
        $values = array_map(static fn (String_ $string): string => $string->value, $definedCalls);

        return \in_array(self::OLD_CONSTANT, $values, true) && \in_array(self::NEW_CONSTANT, $values, true);
    }

    /**
     * Tells whether the statement reads JPATH_PLATFORM as a value, e.g. `JPATH_PLATFORM . '/src'`.
     */
    private function usesConstantAsValue(Stmt $stmt): bool
    {
        $usesConstant = false;

        $this->traverseNodesWithCallable($stmt, function (Node $node) use (&$usesConstant): ?Node {
            if ($node instanceof ConstFetch && $this->isName($node, self::OLD_CONSTANT)) {
                $usesConstant = true;
            }

            return null;
        });

        return $usesConstant;
    }

    private function markStatement(Stmt $stmt): ?Stmt
    {
        $comments = $stmt->getAttribute(AttributeKey::COMMENTS, []);

        foreach ($comments as $comment) {
            // Idempotence: do not stack the same marker on every run.
            if (str_contains($comment->getText(), 'TODO jrector: JPATH_PLATFORM')) {
                return null;
            }
        }

        array_unshift($comments, new Comment(self::TODO_COMMENT));
        $stmt->setAttribute(AttributeKey::COMMENTS, $comments);

        return $stmt;
    }
}
