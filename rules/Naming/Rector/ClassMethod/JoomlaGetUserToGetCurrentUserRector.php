<?php
/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2022 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Rector\Naming\Rector\ClassMethod;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

/**
 * Replaces Factory::getUser() / JFactory::getUser() with $this->getCurrentUser()
 * in BaseModel subclasses.
 *
 * Matched patterns:
 *   Factory::getUser()   → $this->getCurrentUser()
 *   JFactory::getUser()  → $this->getCurrentUser()
 *
 * @since  1.0.0
 * @see    \Rector\Tests\Naming\Rector\ClassMethod\JoomlaGetUserToGetCurrentUserRector\JoomlaGetUserToGetCurrentUserRectorTest
 */
final class JoomlaGetUserToGetCurrentUserRector extends AbstractRector implements ConfigurableRectorInterface
{
	private const FACTORY_FQN  = 'Joomla\CMS\Factory';
	private const STATIC_CALLERS = ['Factory', 'JFactory'];

	/** @var string[] */
	private array $modelParentClasses = [];

	/**
	 * @param string[]  $configuration  Short names or FQNs of parent classes, e.g.
	 *                                  ['BaseModel', '\Joomla\CMS\MVC\Model\BaseModel']
	 */
	public function configure(array $configuration): void
	{
		Assert::allString($configuration);
		$this->modelParentClasses = $configuration;
	}

	public function getNodeTypes(): array
	{
		return [Class_::class];
	}

	public function getRuleDefinition(): RuleDefinition
	{
		return new RuleDefinition(
			'Replace Factory::getUser() / JFactory::getUser() with $this->getCurrentUser() in BaseModel subclasses',
			[
				new CodeSample(
					<<<'CODE_SAMPLE'
class ExampleModel extends BaseModel
{
    public function isAllowed(): bool
    {
        $user = Factory::getUser();
        return $user->authorise('core.edit', 'com_example');
    }
}
CODE_SAMPLE,
					<<<'CODE_SAMPLE'
class ExampleModel extends BaseModel
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
		if (!$this->isModelClass($node))
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

	private function isModelClass(Class_ $class): bool
	{
		if ($class->extends === null)
		{
			return false;
		}

		$parentName = $class->extends->toString();

		if ($this->modelParentClasses !== [])
		{
			foreach ($this->modelParentClasses as $parentClass)
			{
				$normalized = ltrim($parentClass, '\\');

				if ($parentName === $normalized || str_ends_with($parentName, '\\' . $normalized))
				{
					return true;
				}
			}

			return false;
		}

		// Default: match any class whose short name is BaseModel
		$shortName = ltrim((string) strrchr($parentName, '\\'), '\\') ?: $parentName;

		return $shortName === 'BaseModel';
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