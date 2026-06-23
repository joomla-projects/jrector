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
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * In CMSPlugin subclasses:
 *   - Replaces $this->app with $this->getApplication() when the class has a $app property.
 *   - Replaces $this->db  with $this->getDatabase()    when the class has a $db  property.
 *
 * Property detection checks the class's own declarations first (AST), then falls back to
 * PHPStan's ReflectionProvider for inherited properties. Since CMSPlugin always declares
 * both $app and $db, all direct subclasses are treated as having both properties.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla5\PluginPropertyToGetterRector\PluginPropertyToGetterRectorTest
 */
final class PluginPropertyToGetterRector extends AbstractRector
{
    private const PLUGIN_ANCESTOR = 'Joomla\\CMS\\Plugin\\CMSPlugin';
    private const SHORT_ANCESTOR  = 'CMSPlugin';

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
            'Replace $this->app / $this->db with getApplication() / getDatabase() in CMSPlugin subclasses',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class PlgContentExample extends CMSPlugin
{
    public function onContentPrepare(): void
    {
        $app   = $this->app;
        $query = $this->db->getQuery(true);
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class PlgContentExample extends CMSPlugin
{
    public function onContentPrepare(): void
    {
        $app   = $this->getApplication();
        $query = $this->getDatabase()->getQuery(true);
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
        if (!$this->isCmsPluginClass($node)) {
            return null;
        }

        $hasApp = $this->classHasProperty($node, 'app');
        $hasDb  = $this->classHasProperty($node, 'db');

        if (!$hasApp && !$hasDb) {
            return null;
        }

        $hasChanged = false;

        $this->traverseNodesWithCallable($node->stmts, function (Node $subNode) use ($hasApp, $hasDb, &$hasChanged): ?Node {
            if (!$subNode instanceof PropertyFetch) {
                return null;
            }

            if (!$subNode->var instanceof Variable || !$this->isName($subNode->var, 'this')) {
                return null;
            }

            if (!$subNode->name instanceof Identifier) {
                return null;
            }

            $propName = $subNode->name->name;

            if ($hasApp && $propName === 'app') {
                $hasChanged = true;
                return new MethodCall(new Variable('this'), 'getApplication', []);
            }

            if ($hasDb && $propName === 'db') {
                $hasChanged = true;
                return new MethodCall(new Variable('this'), 'getDatabase', []);
            }

            return null;
        });

        return $hasChanged ? $node : null;
    }

    // -------------------------------------------------------------------------

    private function isCmsPluginClass(Class_ $class): bool
    {
        if ($class->extends === null) {
            return false;
        }

        // Fast AST path: direct extension (no reflection needed)
        $parentShortName = $class->extends->getLast();
        $parentFqn       = ltrim($class->extends->toString(), '\\');

        if ($parentFqn === self::PLUGIN_ANCESTOR || $parentShortName === self::SHORT_ANCESTOR) {
            return true;
        }

        // Reflection path: walk the full parent chain
        $className = $this->getName($class);

        if ($className === null || !$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        foreach ($this->reflectionProvider->getClass($className)->getParents() as $parentReflection) {
            if ($parentReflection->getName() === self::PLUGIN_ANCESTOR) {
                return true;
            }
        }

        return false;
    }

    private function classHasProperty(Class_ $class, string $propertyName): bool
    {
        // AST: own property declarations in this class
        foreach ($class->getProperties() as $property) {
            foreach ($property->props as $prop) {
                if ((string) $prop->name === $propertyName) {
                    return true;
                }
            }
        }

        // Fast path: direct CMSPlugin subclass always has $app and $db
        if ($class->extends !== null) {
            $parentShortName = $class->extends->getLast();
            $parentFqn       = ltrim($class->extends->toString(), '\\');

            if ($parentFqn === self::PLUGIN_ANCESTOR || $parentShortName === self::SHORT_ANCESTOR) {
                return true;
            }
        }

        // Reflection path: includes inherited properties from parent classes
        $className = $this->getName($class);

        if ($className === null || !$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        return $this->reflectionProvider->getClass($className)->hasProperty($propertyName);
    }
}
