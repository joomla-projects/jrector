<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla6
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla6\Plugin;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Cleans up the deprecated `$allowLegacyListeners` property in plugins that implement
 * SubscriberInterface.
 *
 * Verified against Joomla 6.1.1, libraries/src/Plugin/CMSPlugin.php: `registerListeners()` starts
 * with
 *
 *     if ($this instanceof SubscriberInterface) {
 *         $this->getDispatcher()->addSubscriber($this);
 *         return;
 *     }
 *
 * so for a plugin implementing SubscriberInterface the property is never read at all. Both the
 * property (`@deprecated 4.3 will be removed in 7.0`) and `registerListeners()` itself
 * (`@deprecated 5.4.0 will be removed in 7.0`) are on their way out.
 *
 * The default mode is therefore `remove`: on a subscriber plugin the property is dead code that
 * has to go before Joomla 7. Mode `set_false` is offered for code bases that still want the
 * explicit declaration.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla6\Plugin\AllowLegacyListenersRector\AllowLegacyListenersRectorTest
 */
final class AllowLegacyListenersRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * Configuration key selecting what happens to the property.
     */
    public const MODE = 'mode';

    /**
     * Remove the property — the default, see the class docblock.
     */
    public const MODE_REMOVE = 'remove';

    /**
     * Keep the property but force it to false.
     */
    public const MODE_SET_FALSE = 'set_false';

    private const PROPERTY_NAME = 'allowLegacyListeners';

    private const PLUGIN_ANCESTOR = 'Joomla\\CMS\\Plugin\\CMSPlugin';

    private const SUBSCRIBER_INTERFACE = 'Joomla\\Event\\SubscriberInterface';

    private string $mode = self::MODE_REMOVE;

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public function configure(array $configuration): void
    {
        $mode = $configuration[self::MODE] ?? self::MODE_REMOVE;

        if ($mode === self::MODE_SET_FALSE || $mode === self::MODE_REMOVE) {
            $this->mode = $mode;
        }
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove the deprecated $allowLegacyListeners property from plugins implementing SubscriberInterface',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

class PlgContentExample extends CMSPlugin implements SubscriberInterface
{
    protected $allowLegacyListeners = true;

    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare'];
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

class PlgContentExample extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare'];
    }
}
CODE_SAMPLE,
                    [self::MODE => self::MODE_REMOVE]
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

        if (!$this->isCmsPluginClass($node) || !$this->implementsSubscriberInterface($node)) {
            return null;
        }

        return $this->mode === self::MODE_REMOVE
            ? $this->removeProperty($node)
            : $this->setPropertyToFalse($node);
    }

    // -------------------------------------------------------------------------
    // Modes
    // -------------------------------------------------------------------------

    private function removeProperty(Class_ $class): ?Class_
    {
        $hasChanged = false;

        foreach ($class->stmts as $key => $stmt) {
            if (!$stmt instanceof Property) {
                continue;
            }

            foreach ($stmt->props as $propertyKey => $propertyItem) {
                if ((string) $propertyItem->name !== self::PROPERTY_NAME) {
                    continue;
                }

                $hasChanged = true;

                // A grouped declaration such as `protected $a, $allowLegacyListeners;` must keep
                // its other properties, so only the single item is dropped.
                if (\count($stmt->props) > 1) {
                    unset($stmt->props[$propertyKey]);
                    $stmt->props = array_values($stmt->props);
                } else {
                    unset($class->stmts[$key]);
                }
            }
        }

        if (!$hasChanged) {
            return null;
        }

        $class->stmts = array_values($class->stmts);

        return $class;
    }

    private function setPropertyToFalse(Class_ $class): ?Class_
    {
        $existingProperty = $this->findPropertyItem($class);

        if ($existingProperty !== null) {
            // Idempotence: already false.
            if ($existingProperty->default instanceof ConstFetch && $this->isName($existingProperty->default, 'false')) {
                return null;
            }

            $existingProperty->default = new ConstFetch(new Name('false'));

            return $class;
        }

        $newProperty = new Property(
            Modifiers::PROTECTED,
            [new PropertyItem(self::PROPERTY_NAME, new ConstFetch(new Name('false')))]
        );

        $class->stmts = $this->insertAfterLastProperty($class->stmts, $newProperty);

        return $class;
    }

    private function findPropertyItem(Class_ $class): ?PropertyItem
    {
        foreach ($class->getProperties() as $property) {
            foreach ($property->props as $propertyItem) {
                if ((string) $propertyItem->name === self::PROPERTY_NAME) {
                    return $propertyItem;
                }
            }
        }

        return null;
    }

    /**
     * Inserts the new property after the last existing property, or before the first method
     * when the class has no properties yet.
     *
     * @param Node\Stmt[] $stmts
     *
     * @return Node\Stmt[]
     */
    private function insertAfterLastProperty(array $stmts, Property $newProperty): array
    {
        $insertIndex = 0;

        foreach ($stmts as $index => $stmt) {
            if ($stmt instanceof Property) {
                $insertIndex = $index + 1;
            }
        }

        // No property yet: place it in front of everything, so it does not end up between methods.
        return array_merge(
            \array_slice($stmts, 0, $insertIndex),
            [$newProperty],
            \array_slice($stmts, $insertIndex)
        );
    }

    // -------------------------------------------------------------------------
    // Class detection
    // -------------------------------------------------------------------------

    private function isCmsPluginClass(Class_ $class): bool
    {
        if ($class->extends === null) {
            return false;
        }

        // Fast AST path: direct extension, no reflection needed.
        if (
            ltrim($class->extends->toString(), '\\') === self::PLUGIN_ANCESTOR
            || $class->extends->getLast() === 'CMSPlugin'
        ) {
            return true;
        }

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

    private function implementsSubscriberInterface(Class_ $class): bool
    {
        // Fast AST path: declared on this very class.
        foreach ($class->implements as $interface) {
            if (
                ltrim($interface->toString(), '\\') === self::SUBSCRIBER_INTERFACE
                || $interface->getLast() === 'SubscriberInterface'
            ) {
                return true;
            }
        }

        // Reflection path: inherited through a parent class or another interface.
        $className = $this->getName($class);

        if ($className === null || !$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        return $this->reflectionProvider->getClass($className)->implementsInterface(self::SUBSCRIBER_INTERFACE);
    }
}
