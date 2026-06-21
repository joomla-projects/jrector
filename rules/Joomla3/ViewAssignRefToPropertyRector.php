<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla3
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla3;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces $this->assignRef('key', $value) / $this->assign('key', $value) with
 * $this->key = $value in Joomla view classes.
 *
 * A class qualifies when it directly or indirectly extends one of:
 *   - Joomla\CMS\MVC\View\HtmlView
 *   - JViewLegacy
 *   - JView
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla3\ViewAssignRefToPropertyRector\ViewAssignRefToPropertyRectorTest
 */
final class ViewAssignRefToPropertyRector extends AbstractRector
{
	private const VIEW_ANCESTORS = [
		'Joomla\CMS\MVC\View\HtmlView',
		'JViewLegacy',
		'JView',
	];

	public function __construct(
		private readonly ReflectionProvider $reflectionProvider,
	) {}

	public function getNodeTypes(): array
	{
		return [Class_::class];
	}

	public function getRuleDefinition(): RuleDefinition
	{
		return new RuleDefinition(
			"Replace \$this->assignRef('key', \$value) with \$this->key = \$value in JView subclasses",
			[
				new CodeSample(
					<<<'CODE_SAMPLE'
class ExampleView extends JView
{
    public function display($tpl = null)
    {
        $items = $this->get('Items');
        $this->assignRef('items', $items);
        $this->assignRef('user', JFactory::getUser());

        parent::display($tpl);
    }
}
CODE_SAMPLE,
					<<<'CODE_SAMPLE'
class ExampleView extends JView
{
    public function display($tpl = null)
    {
        $items = $this->get('Items');
        $this->items = $items;
        $this->user = JFactory::getUser();

        parent::display($tpl);
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
		if (!$this->isViewClass($node))
		{
			return null;
		}

		$hasChanged = false;

		$this->traverseNodesWithCallable($node->stmts, function (Node $subNode) use (&$hasChanged): ?Node {
			if (!$this->isAssignRefCall($subNode))
			{
				return null;
			}

			/** @var MethodCall $subNode */
			$firstArg  = $subNode->args[0];
			$secondArg = $subNode->args[1];

			/** @var String_ $propertyNameNode */
			$propertyNameNode = $firstArg->value;
			$valueExpr        = $secondArg->value;

			$hasChanged = true;

			return new Assign(
				new PropertyFetch(new Variable('this'), $propertyNameNode->value),
				$valueExpr
			);
		});

		return $hasChanged ? $node : null;
	}

	// -------------------------------------------------------------------------

	private function isViewClass(Class_ $class): bool
	{
		if ($class->extends === null)
		{
			return false;
		}

		// Fast AST path: direct extension of a known view class (no reflection needed)
		$parentShortName = $class->extends->getLast();
		$parentFqn       = ltrim($class->extends->toString(), '\\');

		foreach (self::VIEW_ANCESTORS as $ancestor)
		{
			if ($parentFqn === $ancestor || $parentShortName === $ancestor)
			{
				return true;
			}
		}

		// Reflection path: walk the full inheritance chain
		$className = $this->getName($class);

		if ($className === null || !$this->reflectionProvider->hasClass($className))
		{
			return false;
		}

		$classReflection = $this->reflectionProvider->getClass($className);

		while ($classReflection->getParentClass() !== null)
		{
			$classReflection = $classReflection->getParentClass();

			if (in_array($classReflection->getName(), self::VIEW_ANCESTORS, true))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Matches: $this->assign('someKey', $someExpr)
	 *          $this->assignRef('someKey', $someExpr)
	 */
	private function isAssignRefCall(Node $node): bool
	{
		if (!$node instanceof MethodCall)
		{
			return false;
		}

		if (!$node->var instanceof Variable || !$this->isName($node->var, 'this'))
		{
			return false;
		}

		if (!$node->name instanceof Identifier || !in_array($node->name->name, ['assign', 'assignRef'], true))
		{
			return false;
		}

		if (count($node->args) !== 2)
		{
			return false;
		}

		if (!$node->args[0] instanceof Arg || !$node->args[1] instanceof Arg)
		{
			return false;
		}

		return $node->args[0]->value instanceof String_;
	}
}