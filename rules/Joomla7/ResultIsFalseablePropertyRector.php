<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla7
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla7;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Removes the `$resultIsFalseable` property from event classes that use one of the
 * `ResultType*Aware` traits.
 *
 * Joomla 7 removes the flag (`@deprecated 4.3 will be removed in 7.0`) from the traits together
 * with the branch in `typeCheckResult()` that honoured it, so an override in an event class is dead
 * code: a `false` result throws the type's InvalidArgumentException whatever the property says.
 * Whether the event should accept `null` instead (`$resultIsNullable`) is a decision about the
 * listeners and is left to the developer.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla7\ResultIsFalseablePropertyRector\ResultIsFalseablePropertyRectorTest
 */
final class ResultIsFalseablePropertyRector extends AbstractRector
{
    private const PROPERTY_NAME = 'resultIsFalseable';

    /**
     * The traits that carried the flag.
     *
     * @var string[]
     */
    private const TRAITS = [
        'Joomla\\CMS\\Event\\Result\\ResultTypeArrayAware',
        'Joomla\\CMS\\Event\\Result\\ResultTypeFloatAware',
        'Joomla\\CMS\\Event\\Result\\ResultTypeIntegerAware',
        'Joomla\\CMS\\Event\\Result\\ResultTypeNumericAware',
        'Joomla\\CMS\\Event\\Result\\ResultTypeObjectAware',
        'Joomla\\CMS\\Event\\Result\\ResultTypeStringAware',
    ];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove the $resultIsFalseable property, dropped from the ResultType*Aware traits in Joomla 7',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class GetItemsEvent extends AbstractEvent implements ResultAwareInterface
{
    use ResultAware;
    use ResultTypeArrayAware;

    protected $resultIsFalseable = true;
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class GetItemsEvent extends AbstractEvent implements ResultAwareInterface
{
    use ResultAware;
    use ResultTypeArrayAware;
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
        if ($node->isAnonymous() || !$this->usesResultTypeTrait($node)) {
            return null;
        }

        $hasChanged = false;

        foreach ($node->stmts as $key => $stmt) {
            if (!$stmt instanceof Property) {
                continue;
            }

            foreach ($stmt->props as $propertyKey => $propertyItem) {
                if ((string) $propertyItem->name !== self::PROPERTY_NAME) {
                    continue;
                }

                $hasChanged = true;

                // A grouped declaration such as `protected $resultIsNullable, $resultIsFalseable;`
                // keeps its other properties, so only the single item is dropped.
                if (\count($stmt->props) > 1) {
                    unset($stmt->props[$propertyKey]);
                    $stmt->props = array_values($stmt->props);
                } else {
                    unset($node->stmts[$key]);
                }
            }
        }

        if (!$hasChanged) {
            return null;
        }

        $node->stmts = array_values($node->stmts);

        return $node;
    }

    /**
     * The class must be shown to use one of the traits: declared on the class itself, or
     * inherited through a parent that reflection can see. A property of that name in an
     * unrelated class is left alone.
     */
    private function usesResultTypeTrait(Class_ $class): bool
    {
        foreach ($class->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $trait) {
                if (\in_array(ltrim($trait->toString(), '\\'), self::TRAITS, true)) {
                    return true;
                }
            }
        }

        $className = $this->getName($class);

        if ($className === null || !$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        $classReflection = $this->reflectionProvider->getClass($className);

        foreach (self::TRAITS as $trait) {
            if ($classReflection->hasTraitUse($trait)) {
                return true;
            }
        }

        return false;
    }
}
