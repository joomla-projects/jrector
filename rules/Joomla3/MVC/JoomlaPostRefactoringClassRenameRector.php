<?php
/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2026 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Rector\Joomla3\MVC;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitor;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Configuration\Option;
use Rector\Configuration\Parameter\SimpleParameterProvider;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Rector\Renaming\NodeManipulator\ClassRenamer;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

class JoomlaPostRefactoringClassRenameRector extends AbstractRector
{
	use JoomlaNamespaceHandlingTrait;

	public function __construct(
        private readonly RenamedClassHandlerService $renamedClassHandlerService,
        private readonly ClassRenamer $classRenamer
    )
	{}

	/**
	 * @return array<class-string<Node>>
	 */
	public function getNodeTypes(): array
	{
		return [
			Name::class, Property::class, FunctionLike::class, Expression::class, ClassLike::class, Namespace_::class,
			FileNode::class, Use_::class,
		];
	}

	public function getRuleDefinition(): RuleDefinition
	{
		return new RuleDefinition('Replaces defined classes by new ones.', [
			new CodeSample(
				<<<'CODE_SAMPLE'
class ExampleModelFoobar extends \Joomla\CMS\MVC\Model\BaseModel
{
	/**
	 * @return ExampleTableFoobar
	 */
	public function doSomething(): ExampleTableFoobar
	{
		return $this->getTable();
	}
}
CODE_SAMPLE
				, <<<'CODE_SAMPLE'
namespace \Acme\Example\Administrator\Model;

use \Acme\Example\Administrator\Table\FoobarTable;

class FoobarModel extends \Joomla\CMS\MVC\Model\BaseModel
{
	/**
	 * @return FoobarTable
	 */
	public function doSomething(): FoobarTable
	{
		return $this->getTable();
	}
}
CODE_SAMPLE
			),
		]);
	}

	/**
	 * @param   FunctionLike|Name|ClassLike|Expression|Namespace_|Property|FileNode|Use_  $node
	 */
	public function refactor(Node $node): int|Node|null
	{
		$applicationSide = strtolower($this->getApplicationSide());
		$applicationSide = ($applicationSide === 'administrator') ? 'admin' : $applicationSide;

		$oldToNewClasses = $this->renamedClassHandlerService->getOldToNewMap($applicationSide);

		if ($oldToNewClasses === [])
		{
			return null;
		}

		if (!$node instanceof Use_)
		{
			$scope = $node->getAttribute(AttributeKey::SCOPE);

			return $this->classRenamer->renameNode($node, $oldToNewClasses, $scope);
		}

		if (!SimpleParameterProvider::provideBoolParameter(Option::AUTO_IMPORT_NAMES))
		{
			return null;
		}

		return $this->processCleanUpUse($node, $oldToNewClasses);
	}

	/**
	 * @param   array<string, string>  $oldToNewClasses
	 */
	private function processCleanUpUse(Use_ $use, array $oldToNewClasses): ?int
	{
		foreach ($use->uses as $useUse)
		{
			if (!$useUse->alias instanceof Identifier && isset($oldToNewClasses[$useUse->name->toString()]))
			{
				return NodeVisitor::REMOVE_NODE;
			}
		}

		return null;
	}
}
