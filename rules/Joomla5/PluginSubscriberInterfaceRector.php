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

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Adds Joomla\Event\SubscriberInterface and a generated getSubscribedEvents() method to classes
 * that directly or indirectly extend Joomla\CMS\Plugin\CMSPlugin and do not yet implement it.
 *
 * getSubscribedEvents() returns an entry for every public non-magic, non-static method found
 * in the class body, using the method name as both array key and value.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla5\PluginSubscriberInterfaceRector\PluginSubscriberInterfaceRectorTest
 */
final class PluginSubscriberInterfaceRector extends AbstractRector
{
    private const PLUGIN_ANCESTOR  = 'Joomla\\CMS\\Plugin\\CMSPlugin';
    private const SHORT_ANCESTOR   = 'CMSPlugin';
    private const SUBSCRIBER_IFACE = 'Joomla\\Event\\SubscriberInterface';

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
            'Add SubscriberInterface and getSubscribedEvents() to CMSPlugin subclasses',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class PlgContentExample extends CMSPlugin
{
    public function onContentPrepare(): void {}
    public function onUserLogin(): bool { return true; }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class PlgContentExample extends CMSPlugin implements \Joomla\Event\SubscriberInterface
{
    public function onContentPrepare(): void {}
    public function onUserLogin(): bool { return true; }

    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare', 'onUserLogin' => 'onUserLogin'];
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

        if ($this->alreadyImplementsSubscriberInterface($node)) {
            return null;
        }

        $publicMethodNames = $this->collectPublicMethodNames($node);

        $node->implements[] = new Name\FullyQualified(self::SUBSCRIBER_IFACE);
        $node->stmts[]      = $this->buildGetSubscribedEventsMethod($publicMethodNames);

        return $node;
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

    private function alreadyImplementsSubscriberInterface(Class_ $class): bool
    {
        // Fast AST check: direct implements declaration
        foreach ($class->implements as $implement) {
            if (ltrim($implement->toString(), '\\') === self::SUBSCRIBER_IFACE) {
                return true;
            }
        }

        // Reflection check: interface inherited from a parent class
        $className = $this->getName($class);

        if ($className === null || !$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        foreach ($this->reflectionProvider->getClass($className)->getInterfaces() as $iface) {
            if ($iface->getName() === self::SUBSCRIBER_IFACE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collect names of all public, non-static, non-magic methods defined in the class.
     *
     * @return string[]
     */
    private function collectPublicMethodNames(Class_ $class): array
    {
        $names = [];

        foreach ($class->getMethods() as $method) {
            if (!$method->isPublic() || $method->isAbstract() || $method->isStatic()) {
                continue;
            }

            $methodName = (string) $method->name;

            if (str_starts_with($methodName, '__') || $methodName === 'getSubscribedEvents') {
                continue;
            }

            $names[] = $methodName;
        }

        return $names;
    }

    /**
     * Build:  public static function getSubscribedEvents(): array { return [...]; }
     *
     * @param string[] $publicMethodNames
     */
    private function buildGetSubscribedEventsMethod(array $publicMethodNames): ClassMethod
    {
        $arrayItems = [];

        foreach ($publicMethodNames as $name) {
            $arrayItems[] = new ArrayItem(new String_($name), new String_($name));
        }

        return new ClassMethod(
            new Identifier('getSubscribedEvents'),
            [
                'flags'      => Modifiers::PUBLIC | Modifiers::STATIC,
                'returnType' => new Name('array'),
                'stmts'      => [
                    new Return_(
                        new Array_($arrayItems, ['kind' => Array_::KIND_SHORT])
                    ),
                ],
            ]
        );
    }
}
