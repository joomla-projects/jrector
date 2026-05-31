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
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

/**
 * In HtmlView classes:
 *  1. Ensures $model->setUseException(true) follows every $model = $this->getModel() call.
 *  2. Removes if (count($errors = $model->getErrors())) blocks (including leading comments).
 *
 * @since  1.0.0
 * @see    \Rector\Tests\Naming\Rector\ClassMethod\JoomlaHtmlViewExceptionHandlingRector\JoomlaHtmlViewExceptionHandlingRectorTest
 */
final class JoomlaHtmlViewExceptionHandlingRector extends AbstractRector implements ConfigurableRectorInterface
{
	/** @var string[] */
	private array $htmlViewParentClasses = [];

	/** @param string[] $configuration  Short names or FQNs, e.g. ['HtmlView'] */
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
			'Add $model->setUseException(true) after $this->getModel() and remove legacy getErrors() if-blocks in HtmlView classes',
			[
				new CodeSample(
					<<<'CODE_SAMPLE'
class ExampleHtmlView extends HtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();

        // Check for errors.
        if (count($errors = $model->getErrors())) {
            throw new \Exception(implode("\n", $errors));
        }

        $items = $model->getItems();
    }
}
CODE_SAMPLE,
					<<<'CODE_SAMPLE'
class ExampleHtmlView extends HtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $model->setUseException(true);

        $items = $model->getItems();
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

	// -------------------------------------------------------------------------

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

		$shortName = ltrim((string) strrchr($parentName, '\\'), '\\') ?: $parentName;

		return $shortName === 'HtmlView' || str_ends_with($shortName, 'HtmlView');
	}

	private function transformMethod(ClassMethod $classMethod): bool
	{
		if ($classMethod->stmts === null || $classMethod->stmts === [])
		{
			return false;
		}

		$hasChanged = false;

		$newStmts = $this->ensureSetUseException($classMethod->stmts);

		if ($newStmts !== null)
		{
			$classMethod->stmts = $newStmts;
			$hasChanged         = true;
		}

		$filteredStmts = $this->removeGetErrorsIfBlocks($classMethod->stmts);

		if ($filteredStmts !== null)
		{
			$classMethod->stmts = $filteredStmts;
			$hasChanged         = true;
		}

		return $hasChanged;
	}

	/**
	 * Insert $model->setUseException(true) after each $model = $this->getModel()
	 * that is not already followed by that call.
	 *
	 * @param  Node[]  $stmts
	 * @return Node[]|null  Modified list, or null when nothing changed.
	 */
	private function ensureSetUseException(array $stmts): ?array
	{
		$hasChanged = false;
		$result     = [];

		foreach ($stmts as $index => $stmt)
		{
			$result[]      = $stmt;
			$modelVarName  = $this->extractGetModelVarName($stmt);

			if ($modelVarName === null)
			{
				continue;
			}

			// Skip if the very next statement is already setUseException(true)
			$nextStmt = $stmts[$index + 1] ?? null;

			if ($nextStmt !== null && $this->isSetUseException($nextStmt, $modelVarName))
			{
				continue;
			}

			$result[] = new Expression(
				new MethodCall(
					new Variable($modelVarName),
					'setUseException',
					[new Arg(new ConstFetch(new Name('true')))]
				)
			);

			$hasChanged = true;
		}

		return $hasChanged ? $result : null;
	}

	/**
	 * Remove every if (count($errors = $model->getErrors())) statement.
	 * Leading comments are part of the If_ node and are removed automatically.
	 *
	 * @param  Node[]  $stmts
	 * @return Node[]|null  Filtered list, or null when nothing was removed.
	 */
	private function removeGetErrorsIfBlocks(array $stmts): ?array
	{
		$filtered = array_values(
			array_filter($stmts, fn(Node $stmt): bool => !$this->isGetErrorsIfBlock($stmt))
		);

		if (count($filtered) === count($stmts))
		{
			return null;
		}

		return $filtered;
	}

	// -------------------------------------------------------------------------
	// Node pattern matchers
	// -------------------------------------------------------------------------

	/**
	 * Matches: $<name> = $this->getModel()
	 * Returns the variable name, or null if the pattern does not match.
	 */
	private function extractGetModelVarName(Node $stmt): ?string
	{
		if (!$stmt instanceof Expression || !$stmt->expr instanceof Assign)
		{
			return null;
		}

		$assign = $stmt->expr;

		if (!$assign->var instanceof Variable)
		{
			return null;
		}

		if (!$assign->expr instanceof MethodCall)
		{
			return null;
		}

		$call = $assign->expr;

		if (!$call->var instanceof Variable || !$this->isName($call->var, 'this'))
		{
			return null;
		}

		if (!$call->name instanceof Identifier || $call->name->name !== 'getModel')
		{
			return null;
		}

		return $this->getName($assign->var);
	}

	/**
	 * Matches: $<modelVarName>->setUseException(true)
	 */
	private function isSetUseException(Node $stmt, string $modelVarName): bool
	{
		if (!$stmt instanceof Expression || !$stmt->expr instanceof MethodCall)
		{
			return false;
		}

		$call = $stmt->expr;

		if (!$call->var instanceof Variable || !$this->isName($call->var, $modelVarName))
		{
			return false;
		}

		if (!$call->name instanceof Identifier || $call->name->name !== 'setUseException')
		{
			return false;
		}

		if (count($call->args) !== 1 || !$call->args[0] instanceof Arg)
		{
			return false;
		}

		$value = $call->args[0]->value;

		return $value instanceof ConstFetch
			&& strtolower($value->name->toString()) === 'true';
	}

	/**
	 * Matches: if (count($<any> = $<any>->getErrors())) { ... }
	 */
	private function isGetErrorsIfBlock(Node $stmt): bool
	{
		if (!$stmt instanceof If_)
		{
			return false;
		}

		$cond = $stmt->cond;

		if (!$cond instanceof FuncCall)
		{
			return false;
		}

		if (!$cond->name instanceof Name || $cond->name->toString() !== 'count')
		{
			return false;
		}

		if (count($cond->args) !== 1 || !$cond->args[0] instanceof Arg)
		{
			return false;
		}

		$innerExpr = $cond->args[0]->value;

		if (!$innerExpr instanceof Assign || !$innerExpr->var instanceof Variable)
		{
			return false;
		}

		if (!$innerExpr->expr instanceof MethodCall)
		{
			return false;
		}

		$call = $innerExpr->expr;

		return $call->var instanceof Variable
			&& $call->name instanceof Identifier
			&& $call->name->name === 'getErrors';
	}
}