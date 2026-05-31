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
 * Replaces getDbo() calls with getDatabase() in BaseDatabaseModel subclasses.
 *
 * Matched patterns:
 *   $this->getDbo()       → $this->getDatabase()
 *   Factory::getDbo()     → $this->getDatabase()
 *   JFactory::getDbo()    → $this->getDatabase()
 *
 * @since  1.0.0
 * @see    \Rector\Tests\Naming\Rector\ClassMethod\JoomlaGetDboToGetDatabaseRector\JoomlaGetDboToGetDatabaseRectorTest
 */
final class JoomlaGetDboToGetDatabaseRector extends AbstractRector implements ConfigurableRectorInterface
{
	/**
	 * FQN of Joomla\CMS\Factory (without leading backslash, as PHP-Parser stores it).
	 */
	private const FACTORY_FQN = 'Joomla\CMS\Factory';

	/**
	 * Short names of classes whose static getDbo() calls are replaced.
	 * The FQN is always checked in addition.
	 */
	private const STATIC_CALLERS = ['Factory', 'JFactory'];

	/** @var string[] */
	private array $modelParentClasses = [];

	/**
	 * @param string[]  $configuration  Short names or FQNs of parent classes, e.g.
	 *                                  ['BaseDatabaseModel', '\Joomla\CMS\MVC\Model\BaseDatabaseModel']
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
			'Replace getDbo() calls with getDatabase() in BaseDatabaseModel subclasses',
			[
				new CodeSample(
					<<<'CODE_SAMPLE'
class ExampleModel extends BaseDatabaseModel
{
    public function getItems(): array
    {
        $db = $this->getDbo();
        $db = Factory::getDbo();
        $db = JFactory::getDbo();

        return $db->loadObjectList();
    }
}
CODE_SAMPLE,
					<<<'CODE_SAMPLE'
class ExampleModel extends BaseDatabaseModel
{
    public function getItems(): array
    {
        $db = $this->getDatabase();
        $db = $this->getDatabase();
        $db = $this->getDatabase();

        return $db->loadObjectList();
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
			if (!$this->isGetDboCall($subNode))
			{
				return null;
			}

			$hasChanged = true;

			return new MethodCall(new Variable('this'), 'getDatabase');
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

		// Default: match any class whose short name is BaseDatabaseModel
		$shortName = ltrim((string) strrchr($parentName, '\\'), '\\') ?: $parentName;

		return $shortName === 'BaseDatabaseModel';
	}

	private function isGetDboCall(Node $node): bool
	{
		return $this->isThisGetDboCall($node) || $this->isStaticGetDboCall($node);
	}

	/**
	 * Matches: $this->getDbo()
	 */
	private function isThisGetDboCall(Node $node): bool
	{
		if (!$node instanceof MethodCall)
		{
			return false;
		}

		if (!$node->var instanceof Variable || !$this->isName($node->var, 'this'))
		{
			return false;
		}

		if (!$node->name instanceof Identifier || $node->name->name !== 'getDbo')
		{
			return false;
		}

		return count($node->args) === 0;
	}

	/**
	 * Matches: Factory::getDbo()  |  JFactory::getDbo()  |  \Joomla\CMS\Factory::getDbo()
	 */
	private function isStaticGetDboCall(Node $node): bool
	{
		if (!$node instanceof StaticCall)
		{
			return false;
		}

		if (!$node->name instanceof Identifier || $node->name->name !== 'getDbo')
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
