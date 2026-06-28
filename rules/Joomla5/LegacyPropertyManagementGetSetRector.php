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
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces $this->get('key', $default) and $this->set('key', $value) calls with
 * direct property access in classes that use LegacyPropertyManagementTrait.
 *
 * Transformations:
 *   $this->get('key', $default)  →  $this->key ?? $default
 *   $this->get('key')            →  $this->key ?? null
 *   $this->set('key', $value)    →  $this->key = $value
 *
 * Only calls where the receiver is $this are handled. The first argument must be
 * a string literal — dynamic keys cannot be converted to a property access.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla5\LegacyPropertyManagementGetSetRector\LegacyPropertyManagementGetSetRectorTest
 */
final class LegacyPropertyManagementGetSetRector extends AbstractRector
{
    private const TRAIT_SHORT = 'LegacyPropertyManagementTrait';
    private const TRAIT_FQN   = 'Joomla\CMS\Object\LegacyPropertyManagementTrait';

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
            'Replace $this->get()/set() with direct property access in classes using LegacyPropertyManagementTrait',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class ExampleView
{
    use \Joomla\CMS\Object\LegacyPropertyManagementTrait;

    public function display(): void
    {
        $title = $this->get('title', '');
        $this->set('active', true);
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class ExampleView
{
    use \Joomla\CMS\Object\LegacyPropertyManagementTrait;

    public function display(): void
    {
        $title = $this->title ?? '';
        $this->active = true;
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
        if (!$this->classUsesLegacyTrait($node)) {
            return null;
        }

        $hasChanged = false;

        $this->traverseNodesWithCallable($node->stmts, function (Node $subNode) use (&$hasChanged): ?Node {
            if ($this->isThisGetCall($subNode)) {
                $replacement = $this->buildGetReplacement($subNode);

                if ($replacement !== null) {
                    $hasChanged = true;

                    return $replacement;
                }
            }

            if ($this->isThisSetCall($subNode)) {
                $replacement = $this->buildSetReplacement($subNode);

                if ($replacement !== null) {
                    $hasChanged = true;

                    return $replacement;
                }
            }

            return null;
        });

        return $hasChanged ? $node : null;
    }

    // -------------------------------------------------------------------------
    // Replacement builders
    // -------------------------------------------------------------------------

    /**
     * $this->get('key', $default)  →  $this->key ?? $default
     * $this->get('key')            →  $this->key ?? null
     */
    private function buildGetReplacement(Node $node): ?Node
    {
        /** @var MethodCall $node */
        $firstArg = $node->args[0] ?? null;

        if (!$firstArg instanceof Arg || !$firstArg->value instanceof String_) {
            return null;
        }

        $key           = $firstArg->value->value;
        $propertyFetch = new PropertyFetch($node->var, new Identifier($key));

        $secondArg = $node->args[1] ?? null;
        $default   = ($secondArg instanceof Arg)
            ? $secondArg->value
            : new ConstFetch(new Name('null'));

        return new Coalesce($propertyFetch, $default);
    }

    /**
     * $this->set('key', $value)  →  $this->key = $value
     */
    private function buildSetReplacement(Node $node): ?Node
    {
        /** @var MethodCall $node */
        $firstArg = $node->args[0] ?? null;

        if (!$firstArg instanceof Arg || !$firstArg->value instanceof String_) {
            return null;
        }

        $secondArg = $node->args[1] ?? null;

        if (!$secondArg instanceof Arg) {
            return null;
        }

        $key           = $firstArg->value->value;
        $propertyFetch = new PropertyFetch($node->var, new Identifier($key));

        return new Assign($propertyFetch, $secondArg->value);
    }

    // -------------------------------------------------------------------------
    // Call detectors
    // -------------------------------------------------------------------------

    /**
     * Matches $this->get('<literal-key>', ...) calls.
     */
    private function isThisGetCall(Node $node): bool
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

        if (\count($node->args) < 1) {
            return false;
        }

        $firstArg = $node->args[0];

        return $firstArg instanceof Arg && $firstArg->value instanceof String_;
    }

    /**
     * Matches $this->set('<literal-key>', $value) calls.
     */
    private function isThisSetCall(Node $node): bool
    {
        if (!$node instanceof MethodCall) {
            return false;
        }

        if (!$node->var instanceof Variable || !$this->isName($node->var, 'this')) {
            return false;
        }

        if (!$node->name instanceof Identifier || $node->name->name !== 'set') {
            return false;
        }

        if (\count($node->args) < 2) {
            return false;
        }

        $firstArg = $node->args[0];

        return $firstArg instanceof Arg && $firstArg->value instanceof String_;
    }

    // -------------------------------------------------------------------------
    // Trait detection
    // -------------------------------------------------------------------------

    /**
     * Returns true when the class (directly or via a parent) uses LegacyPropertyManagementTrait.
     */
    private function classUsesLegacyTrait(Class_ $class): bool
    {
        // Fast AST path: direct use statement in this class body
        foreach ($class->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $traitName) {
                $name = ltrim($traitName->toString(), '\\');

                if ($name === self::TRAIT_SHORT || $name === self::TRAIT_FQN) {
                    return true;
                }
            }
        }

        // Reflection path: trait may be inherited from a parent class
        $className = $this->getName($class);

        if ($className === null || !$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        foreach ($this->reflectionProvider->getClass($className)->getTraits(true) as $traitReflection) {
            if ($traitReflection->getName() === self::TRAIT_FQN) {
                return true;
            }
        }

        return false;
    }
}
