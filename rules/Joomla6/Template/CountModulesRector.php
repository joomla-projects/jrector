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
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Type\ObjectType;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Splits `countModules()` calls that pass a condition string into individual calls.
 *
 * Verified against Joomla 6.1.1, libraries/src/Document/HtmlDocument.php:
 *
 *     public function countModules(string $positionName, bool $withContentOnly = false)
 *
 * The parameter is a plain, strictly typed position name — the expression evaluation is gone
 * entirely. A call such as `countModules('a and b')` therefore no longer counts anything; it
 * silently looks for a position literally named "a and b" and returns 0.
 *
 * The generated calls deliberately do NOT pass `true` as the second argument. The old expression
 * form counted modules with the default `$withContentOnly = false`, so adding `true` would
 * silently switch to "only modules that actually render content" and change which branches of a
 * template are taken. Set WITH_CONTENT_ONLY if that stricter counting is what you want.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla6\Template\CountModulesRector\CountModulesRectorTest
 */
final class CountModulesRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * Configuration key: pass `true` as the second argument of the generated calls.
     */
    public const WITH_CONTENT_ONLY = 'with_content_only';

    private const DOCUMENT_TYPE = 'Joomla\\CMS\\Document\\HtmlDocument';

    private bool $withContentOnly = false;

    /**
     * @param array<string, mixed> $configuration
     */
    public function configure(array $configuration): void
    {
        $this->withContentOnly = (bool) ($configuration[self::WITH_CONTENT_ONLY] ?? false);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Split countModules() condition strings into individual countModules() calls',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
if ($this->countModules('sidebar-left and sidebar-right')) {
    echo 'both';
}

$count = $this->countModules('top-a + top-b');
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
if ($this->countModules('sidebar-left') && $this->countModules('sidebar-right')) {
    echo 'both';
}

$count = $this->countModules('top-a') + $this->countModules('top-b');
CODE_SAMPLE,
                    [self::WITH_CONTENT_ONLY => false]
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    /**
     * @param MethodCall $node
     */
    public function refactor(Node $node): ?Expr
    {
        if (!$this->isName($node->name, 'countModules')) {
            return null;
        }

        // A second argument means the call was already written for the new signature.
        if (\count($node->args) !== 1) {
            return null;
        }

        if (!$this->isDocumentContext($node->var)) {
            return null;
        }

        $firstArg = $node->args[0];

        if (!$firstArg instanceof Arg || !$firstArg->value instanceof String_) {
            return null;
        }

        $parsed = $this->parseCondition($firstArg->value->value);

        if ($parsed === null) {
            return null;
        }

        [$operator, $positions] = $parsed;

        $calls = array_map(
            fn (string $position): MethodCall => $this->createCountModulesCall($node->var, $position),
            $positions
        );

        return $this->combine($calls, $operator);
    }

    // -------------------------------------------------------------------------

    /**
     * Splits the condition into an operator and its position names.
     *
     * Returns null whenever the expression cannot be reconstructed unambiguously: a single
     * position (nothing to do), mixed operators, or parentheses.
     *
     * @return array{0: string, 1: string[]}|null
     */
    private function parseCondition(string $condition): ?array
    {
        $condition = trim($condition);

        // Parentheses carry a precedence the rule must not guess at.
        if (str_contains($condition, '(') || str_contains($condition, ')')) {
            return null;
        }

        $hasPlus = str_contains($condition, '+');

        // `and`/`or` are matched case insensitively and tolerate repeated whitespace.
        $parts = preg_split('/\s+(and|or)\s+/i', $condition, -1, \PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return null;
        }

        if (\count($parts) > 1) {
            // Mixing the boolean operators with `+` is ambiguous.
            if ($hasPlus) {
                return null;
            }

            $positions = [];
            $operators = [];

            foreach ($parts as $index => $part) {
                if ($index % 2 === 1) {
                    $operators[] = strtolower($part);

                    continue;
                }

                $positions[] = trim($part);
            }

            // 'a and b or c' — the intended precedence is not recoverable.
            if (\count(array_unique($operators)) !== 1) {
                return null;
            }

            return $this->isValidPositionList($positions) ? [$operators[0], $positions] : null;
        }

        if (!$hasPlus) {
            // A plain position name is already the supported form.
            return null;
        }

        $positions = array_map('trim', explode('+', $condition));

        return $this->isValidPositionList($positions) ? ['+', $positions] : null;
    }

    /**
     * @param string[] $positions
     */
    private function isValidPositionList(array $positions): bool
    {
        if (\count($positions) < 2) {
            return false;
        }

        foreach ($positions as $position) {
            // A position name never contains whitespace; if it does, the string held something
            // else than a plain list of positions.
            if ($position === '' || preg_match('/\s/', $position) === 1) {
                return false;
            }
        }

        return true;
    }

    private function createCountModulesCall(Expr $caller, string $position): MethodCall
    {
        $args = [new Arg(new String_($position))];

        if ($this->withContentOnly) {
            $args[] = new Arg(new ConstFetch(new Name('true')));
        }

        return new MethodCall($caller, 'countModules', $args);
    }

    /**
     * @param MethodCall[] $calls
     */
    private function combine(array $calls, string $operator): Expr
    {
        $combined = array_shift($calls);

        foreach ($calls as $call) {
            $combined = match ($operator) {
                'and'   => new BooleanAnd($combined, $call),
                'or'    => new BooleanOr($combined, $call),
                default => new Plus($combined, $call),
            };
        }

        return $combined;
    }

    /**
     * Decides whether the receiver is the document.
     *
     * When the type resolves to concrete classes, one of them has to be an HtmlDocument —
     * otherwise an unrelated `countModules()` on some other class would be rewritten.
     *
     * When nothing resolves, only `$this` is accepted: that is the template file case, where
     * `$this` is the document but there is no class declaration for PHPStan to work from.
     */
    private function isDocumentContext(Expr $caller): bool
    {
        $classNames = $this->getType($caller)->getObjectClassNames();

        if ($classNames === []) {
            return $caller instanceof Variable && $caller->name === 'this';
        }

        $documentType = new ObjectType(self::DOCUMENT_TYPE);

        foreach ($classNames as $className) {
            if ($documentType->isSuperTypeOf(new ObjectType($className))->yes()) {
                return true;
            }
        }

        return false;
    }
}
