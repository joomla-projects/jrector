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
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces Factory::getUser() / JFactory::getUser() with $this->getCurrentUser()
 * in classes that implement \Joomla\CMS\User\CurrentUserInterface directly or indirectly.
 *
 * Matched patterns:
 *   Factory::getUser()   → $this->getCurrentUser()
 *   JFactory::getUser()  → $this->getCurrentUser()
 *
 * The class must implement \Joomla\CMS\User\CurrentUserInterface, either directly
 * in its implements list or through a parent class. Indirect detection requires
 * the Joomla 4 class hierarchy to be available to PHPStan (e.g. via autoloading).
 *
 * @since  1.0.0
 * @see    \Rector\Tests\Naming\Rector\ClassMethod\JoomlaCurrentUserInterfaceGetUserRector\CurrentUserInterfaceGetUserRectorTest
 */
final class CurrentUserInterfaceGetUserRector extends AbstractRector
{
	private const FACTORY_FQN     = 'Joomla\CMS\Factory';
	private const STATIC_CALLERS  = ['Factory', 'JFactory'];
	private const TARGET_INTERFACE = 'Joomla\CMS\User\CurrentUserInterface';

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
			'Replace Factory::getUser() / JFactory::getUser() with $this->getCurrentUser() in classes implementing CurrentUserInterface',
			[
				new CodeSample(
					<<<'CODE_SAMPLE'
class ExampleController implements \Joomla\CMS\User\CurrentUserInterface
{
    public function isAllowed(): bool
    {
        $user = Factory::getUser();
        return $user->authorise('core.edit', 'com_example');
    }
}
CODE_SAMPLE,
					<<<'CODE_SAMPLE'
class ExampleController implements \Joomla\CMS\User\CurrentUserInterface
{
    public function isAllowed(): bool
    {
        $user = $this->getCurrentUser();
        return $user->authorise('core.edit', 'com_example');
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
		if (!$this->implementsCurrentUserInterface($node))
		{
			return null;
		}

		$hasChanged = false;

		$this->traverseNodesWithCallable($node->stmts, function (Node $subNode) use (&$hasChanged): ?Node {
			if (!$this->isGetUserStaticCall($subNode))
			{
				return null;
			}

			$hasChanged = true;

			return new MethodCall(new Variable('this'), 'getCurrentUser');
		});

		return $hasChanged ? $node : null;
	}

	// -------------------------------------------------------------------------

	private function implementsCurrentUserInterface(Class_ $class): bool
	{
		// Direct: check the implements list in the AST (no reflection needed)
		foreach ($class->implements as $implement)
		{
			if (ltrim($implement->toString(), '\\') === self::TARGET_INTERFACE)
			{
				return true;
			}
		}

		// Indirect: use PHPStan reflection to detect implementation through parent classes.
		// Requires the Joomla 4 class hierarchy to be available to PHPStan.
		$className = $this->getName($class);

		if ($className === null)
		{
			return false;
		}

		if (!$this->reflectionProvider->hasClass($className)
			|| !$this->reflectionProvider->hasClass(self::TARGET_INTERFACE))
		{
			return false;
		}

		return $this->reflectionProvider->getClass($className)->implementsInterface(self::TARGET_INTERFACE);
	}

	/**
	 * Matches: Factory::getUser()  |  JFactory::getUser()  |  \Joomla\CMS\Factory::getUser()
	 */
	private function isGetUserStaticCall(Node $node): bool
	{
		if (!$node instanceof StaticCall)
		{
			return false;
		}

		if (!$node->name instanceof Identifier || $node->name->name !== 'getUser')
		{
			return false;
		}

		if (count($node->args) !== 0)
		{
			return false;
		}

		if (!$node->class instanceof Name)
		{
			return false;
		}

		$callerName = ltrim($node->class->toString(), '\\');

		return in_array($callerName, [...self::STATIC_CALLERS, self::FACTORY_FQN], true);
	}
}
