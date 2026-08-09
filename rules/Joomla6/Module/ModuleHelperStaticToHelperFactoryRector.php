<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla6
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla6\Module;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUse;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Moves module helpers from static calls onto the HelperFactory.
 *
 * Module helpers are obtained through the DI container. Static helpers cannot be tested,
 * overridden or registered in the service provider.
 *
 * The rule covers both sides of the change in one pass, on purpose:
 *
 *   - in a dispatcher, `FooHelper::getItems(...)` becomes
 *     `$this->getHelperFactory()->getHelper('FooHelper')->getItems(...)`, and the class gains
 *     `HelperFactoryAwareInterface` plus `HelperFactoryAwareTrait`;
 *   - in the helper class itself, `public static function` becomes `public function` and
 *     `self::`/`static::` calls become `$this->`.
 *
 * Splitting this into two rules would allow running only one half, and each half alone breaks
 * the code: converted call sites against a still-static helper only raise a deprecation, but a
 * converted helper still called statically is a fatal error.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla6\Module\ModuleHelperStaticToHelperFactoryRector\ModuleHelperStaticToHelperFactoryRectorTest
 */
final class ModuleHelperStaticToHelperFactoryRector extends AbstractRector
{
    private const DISPATCHER_ANCESTOR = 'Joomla\\CMS\\Dispatcher\\AbstractModuleDispatcher';

    private const HELPER_FACTORY_INTERFACE = 'Joomla\\CMS\\Helper\\HelperFactoryAwareInterface';

    private const HELPER_FACTORY_TRAIT = 'Joomla\\CMS\\Helper\\HelperFactoryAwareTrait';

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace static module helper calls with the HelperFactory and turn helper methods into instance methods',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData(): array
    {
        $data          = parent::getLayoutData();
        $data['items'] = FooHelper::getItems($data['params'], $this->getApplication());

        return $data;
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class Dispatcher extends AbstractModuleDispatcher implements \Joomla\CMS\Helper\HelperFactoryAwareInterface
{
    use \Joomla\CMS\Helper\HelperFactoryAwareTrait;
    protected function getLayoutData(): array
    {
        $data          = parent::getLayoutData();
        $data['items'] = $this->getHelperFactory()->getHelper('FooHelper')->getItems($data['params'], $this->getApplication());

        return $data;
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
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($node->isAnonymous()) {
            return null;
        }

        if ($this->isModuleDispatcher($node)) {
            return $this->refactorDispatcher($node);
        }

        if ($this->isModuleHelperClass($node)) {
            return $this->refactorHelperClass($node);
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Part A — the calling side, i.e. the dispatcher
    // -------------------------------------------------------------------------

    private function refactorDispatcher(Class_ $class): ?Class_
    {
        $hasChanged = false;

        $this->traverseNodesWithCallable($class, function (Node $subNode) use (&$hasChanged): ?Node {
            if (!$subNode instanceof StaticCall || !$subNode->class instanceof Name) {
                return null;
            }

            if (!$subNode->name instanceof Node\Identifier) {
                return null;
            }

            $calledClass = $this->getName($subNode->class);

            if ($calledClass === null || !$this->isModuleHelperName($calledClass)) {
                return null;
            }

            $hasChanged = true;

            $shortName = $subNode->class->getLast();

            $getHelper = new MethodCall(
                new MethodCall(new Variable('this'), 'getHelperFactory'),
                'getHelper',
                [new Arg(new String_($shortName))]
            );

            return new MethodCall($getHelper, $subNode->name->toString(), $subNode->args);
        });

        if (!$hasChanged) {
            return null;
        }

        $this->addHelperFactoryAwareness($class);

        return $class;
    }

    private function addHelperFactoryAwareness(Class_ $class): void
    {
        $implementsAlready = false;

        foreach ($class->implements as $interface) {
            if ($interface->getLast() === 'HelperFactoryAwareInterface') {
                $implementsAlready = true;
                break;
            }
        }

        if (!$implementsAlready) {
            $class->implements[] = new Name\FullyQualified(self::HELPER_FACTORY_INTERFACE);
        }

        $usesTraitAlready = false;

        foreach ($class->stmts as $stmt) {
            if (!$stmt instanceof TraitUse) {
                continue;
            }

            foreach ($stmt->traits as $trait) {
                if ($trait->getLast() === 'HelperFactoryAwareTrait') {
                    $usesTraitAlready = true;
                    break 2;
                }
            }
        }

        if (!$usesTraitAlready) {
            array_unshift($class->stmts, new TraitUse([new Name\FullyQualified(self::HELPER_FACTORY_TRAIT)]));
        }
    }

    // -------------------------------------------------------------------------
    // Part B — the helper class itself
    // -------------------------------------------------------------------------

    private function refactorHelperClass(Class_ $class): ?Class_
    {
        // Late static binding cannot be reasoned about safely across a hierarchy.
        if ($class->extends !== null) {
            return null;
        }

        $hasChanged = false;

        foreach ($class->getMethods() as $method) {
            if (!$method->isPublic() || !$method->isStatic() || $method->stmts === null) {
                continue;
            }

            // A method reading a static property of its own class has to stay static.
            if ($this->usesOwnStaticProperty($method)) {
                continue;
            }

            $method->flags &= ~Modifiers::STATIC;
            $hasChanged = true;
        }

        if (!$hasChanged) {
            return null;
        }

        // self::foo() / static::foo() now have to go through the instance.
        $this->traverseNodesWithCallable($class, function (Node $subNode): ?Node {
            if (!$subNode instanceof StaticCall || !$subNode->class instanceof Name) {
                return null;
            }

            $calledClass = $subNode->class->toString();

            if ($calledClass !== 'self' && $calledClass !== 'static') {
                return null;
            }

            if (!$subNode->name instanceof Node\Identifier) {
                return null;
            }

            return new MethodCall(new Variable('this'), $subNode->name->toString(), $subNode->args);
        });

        return $class;
    }

    private function usesOwnStaticProperty(Node\Stmt\ClassMethod $method): bool
    {
        $usesStaticProperty = false;

        $this->traverseNodesWithCallable($method, static function (Node $node) use (&$usesStaticProperty): ?Node {
            if (!$node instanceof StaticPropertyFetch || !$node->class instanceof Name) {
                return null;
            }

            $className = $node->class->toString();

            if ($className === 'self' || $className === 'static') {
                $usesStaticProperty = true;
            }

            return null;
        });

        return $usesStaticProperty;
    }

    // -------------------------------------------------------------------------
    // Detection
    // -------------------------------------------------------------------------

    /**
     * A module helper is either namespaced as `...\Module\<Name>\<Client>\Helper\<X>Helper`,
     * or lives in a `...\Helper` namespace with a short name ending in `Helper`.
     *
     * Core helpers such as ModuleHelper and HTMLHelper, and component helpers, are excluded —
     * they are not resolved through the module HelperFactory.
     */
    private function isModuleHelperName(string $className): bool
    {
        $className = ltrim($className, '\\');

        // Core helpers live under Joomla\CMS\ and are never module helpers.
        if (str_starts_with($className, 'Joomla\\CMS\\') || str_starts_with($className, 'Joomla\\')) {
            return false;
        }

        if (str_contains($className, '\\Component\\')) {
            return false;
        }

        $parts     = explode('\\', $className);
        $shortName = end($parts);

        if (!str_ends_with($shortName, 'Helper') || $shortName === 'Helper') {
            return false;
        }

        // The namespace segment directly above the class has to be `Helper`.
        if (\count($parts) < 2 || $parts[\count($parts) - 2] !== 'Helper') {
            return false;
        }

        return str_contains($className, '\\Module\\');
    }

    private function isModuleHelperClass(Class_ $class): bool
    {
        $className = $this->getName($class);

        return $className !== null && $this->isModuleHelperName($className);
    }

    private function isModuleDispatcher(Class_ $class): bool
    {
        if ($class->extends === null) {
            return false;
        }

        if (
            ltrim($class->extends->toString(), '\\') === self::DISPATCHER_ANCESTOR
            || $class->extends->getLast() === 'AbstractModuleDispatcher'
        ) {
            return true;
        }

        $className = $this->getName($class);

        if ($className === null || !$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        foreach ($this->reflectionProvider->getClass($className)->getParents() as $parentReflection) {
            if ($parentReflection->getName() === self::DISPATCHER_ANCESTOR) {
                return true;
            }
        }

        return false;
    }
}
