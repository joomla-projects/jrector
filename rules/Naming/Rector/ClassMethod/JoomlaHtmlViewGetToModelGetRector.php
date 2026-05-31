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
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

/**
 * Replaces $this->get('Items') calls in HtmlView classes with the equivalent model getter.
 *
 * The first replacement per method also prepends $model = $this->getModel().
 *
 * @since  1.0.0
 * @see    \Rector\Tests\Naming\Rector\ClassMethod\JoomlaHtmlViewGetToModelGetRector\JoomlaHtmlViewGetToModelGetRectorTest
 */
final class JoomlaHtmlViewGetToModelGetRector extends AbstractRector implements ConfigurableRectorInterface
{
	/**
	 * List of parent class names (short or FQN) that identify HtmlView classes.
	 *
	 * @var string[]
	 */
	private array $htmlViewParentClasses = [];

	/**
	 * @param string[] $configuration  Short names or FQNs, e.g. ['HtmlView', '\Joomla\CMS\MVC\View\HtmlView']
	 */
	public function configure(array $configuration): void
	{
		Assert::allString($configuration);
		$this->htmlViewParentClasses = $configuration;
	}

	public function getNodeTypes(): array
	{
		return [Class_::class];
	}

	public function getRuleDefinition(): RuleDefinition
	{
		return new RuleDefinition(
			"Replace \$this->get('Items') with \$model->getItems() in HtmlView classes",
			[
				new CodeSample(
					<<<'CODE_SAMPLE'
class ExampleHtmlView extends HtmlView
{
    public function display($tpl = null)
    {
        $items      = $this->get('Items');
        $pagination = $this->get('Pagination');
    }
}
CODE_SAMPLE,
					<<<'CODE_SAMPLE'
class ExampleHtmlView extends HtmlView
{
    public function display($tpl = null)
    {
        $model      = $this->getModel();
        $items      = $model->getItems();
        $pagination = $model->getPagination();
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
		if (!$this->isHtmlViewClass($node))
		{
			return null;
		}

		$hasChanged = false;

		foreach ($node->getMethods() as $classMethod)
		{
			if ($this->transformMethod($classMethod))
			{
				$hasChanged = true;
			}
		}

		return $hasChanged ? $node : null;
	}

	/**
	 * Check if a class is an HtmlView class based on its parent.
	 *
	 * Uses the configured list if provided; otherwise defaults to checking
	 * whether the parent class name ends with 'HtmlView'.
	 */
	private function isHtmlViewClass(Class_ $class): bool
	{
		if ($class->extends === null)
		{
			return false;
		}

		$parentName = $class->extends->toString();

		if ($this->htmlViewParentClasses !== [])
		{
			foreach ($this->htmlViewParentClasses as $viewParentClass)
			{
				$normalized = ltrim($viewParentClass, '\\');

				if ($parentName === $normalized || str_ends_with($parentName, '\\' . $normalized))
				{
					return true;
				}
			}

			return false;
		}

		// Default heuristic: parent class (short name) ends with 'HtmlView'
		$shortName = ltrim((string) strrchr($parentName, '\\'), '\\') ?: $parentName;

		return $shortName === 'HtmlView' || str_ends_with($shortName, 'HtmlView');
	}

	/**
	 * Replace all $this->get('...') calls in a method with $model->get...() calls.
	 * Prepends $model = $this->getModel() if not already present.
	 *
	 * @return bool Whether the method was changed.
	 */
	private function transformMethod(ClassMethod $classMethod): bool
	{
		if ($classMethod->stmts === null || $classMethod->stmts === [])
		{
			return false;
		}

		// First pass: detect whether any $this->get('...') calls exist
		$hasGetCall = false;

		$this->traverseNodesWithCallable($classMethod->stmts, function (Node $subNode) use (&$hasGetCall): ?Node {
			if ($this->isViewGetCall($subNode))
			{
				$hasGetCall = true;
			}

			return null;
		});

		if (!$hasGetCall)
		{
			return false;
		}

		$modelAlreadyDefined = $this->isModelVariableDefined($classMethod);

		// Second pass: replace $this->get('Foo') with $model->getFoo()
		$this->traverseNodesWithCallable($classMethod->stmts, function (Node $subNode): ?Node {
			if (!$this->isViewGetCall($subNode))
			{
				return null;
			}

			/** @var MethodCall $subNode */
			/** @var Arg $firstArg */
			$firstArg = $subNode->args[0];
			/** @var String_ $stringNode */
			$stringNode = $firstArg->value;
			$newMethodName = 'get' . ucfirst($stringNode->value);

			return new MethodCall(new Variable('model'), $newMethodName);
		});

		// Prepend $model = $this->getModel() only once per method
		if (!$modelAlreadyDefined)
		{
			array_unshift(
				$classMethod->stmts,
				new Expression(
					new Assign(
						new Variable('model'),
						new MethodCall(new Variable('this'), 'getModel')
					)
				)
			);
		}

		return true;
	}

	/**
	 * Detect a $this->get('SomeString') call.
	 */
	private function isViewGetCall(Node $node): bool
	{
		if (!$node instanceof MethodCall)
		{
			return false;
		}

		if (!$node->var instanceof Variable || !$this->isName($node->var, 'this'))
		{
			return false;
		}

		if (!$node->name instanceof Identifier || $node->name->name !== 'get')
		{
			return false;
		}

		if (count($node->args) !== 1)
		{
			return false;
		}

		$arg = $node->args[0];

		return $arg instanceof Arg && $arg->value instanceof String_;
	}

	/**
	 * Check whether $model is already assigned somewhere in the method body.
	 */
	private function isModelVariableDefined(ClassMethod $classMethod): bool
	{
		if ($classMethod->stmts === null)
		{
			return false;
		}

		$isDefined = false;

		$this->traverseNodesWithCallable($classMethod->stmts, function (Node $node) use (&$isDefined): ?Node {
			if ($node instanceof Assign
				&& $node->var instanceof Variable
				&& $this->isName($node->var, 'model'))
			{
				$isDefined = true;
			}

			return null;
		});

		return $isDefined;
	}
}