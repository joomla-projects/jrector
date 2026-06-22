<?php
/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2026 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare (strict_types=1);

namespace Joomla\Rector\Joomla3\MVC;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * A Rector rule to namespace legacy Joomla 3 form rule classes into Joomla 4+ MVC namespaced classes
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla3\MVC\FormRulesRector\FormRulesRectorTest
 */
final class FormRulesRector extends LegacyMVCToJ4Rector implements ConfigurableRectorInterface
{
	use JoomlaNamespaceHandlingTrait;

	/**
	 * Get the rule definition.
	 *
	 * This was used to generate the initial test fixture.
	 *
	 * @return  RuleDefinition
	 * @throws  \Symplify\RuleDocGenerator\Exception\PoorDocumentationException
	 * @since   1.0.0
	 */
	public function getRuleDefinition(): RuleDefinition
	{
		return new RuleDefinition('Convert legacy Joomla 3 Helper class names into Joomla 4 namespaced ones.', [
			new CodeSample(
				<<<'CODE_SAMPLE'
class JFormRuleExample extends \Joomla\CMS\Form\FormRule
{
}
CODE_SAMPLE
				, <<<'CODE_SAMPLE'
namespace Acme\Example\Administrator\Rule;

class ExampleRule extends \Joomla\CMS\Form\FormRule
{
}
CODE_SAMPLE
			),
		]);
	}

	public function refactor(Node $node): ?Node
	{
		// Makes sure the immediate path is models/fields
		$filePath = $this->getFile()->getFilePath();
		$filePath = str_replace('\\', '/', $filePath);

		if (strpos($filePath, '/models/rules/') === false)
		{
			return null;
		}

		return parent::refactor($node);
	}

	/**
	 * Process a Name or Identifier node but only if necessary!
	 *
	 * @param   Name|Identifier  $node  The node to possibly refactor
	 *
	 * @return  Identifier|Name|null  The refactored node; NULL if no refactoring was necessary / possible.
	 * @since   1.0.0
	 */
	protected function processNameOrIdentifier($node, bool $isNewFile = false): ?Node
	{
		// no name → skip
		if ($node->toString() === '')
		{
			return null;
		}

		$nodeName = $this->getName($node);

		if ($nodeName === null)
		{
			return null;
		}

		// The class name must begin with a form of "JFormRule".
		if (!str_starts_with($nodeName, 'JFormRule'))
		{
			return null;
		}

		foreach ($this->legacyPrefixesToNamespaces as $legacyPrefixToNamespace)
		{
			$prefix          = substr($nodeName, 0, 5);
			$excludedClasses = $legacyPrefixToNamespace->getExcludedClasses();

			if ($excludedClasses !== [] && in_array($nodeName, $excludedClasses, true))
			{
				return null;
			}

			if ($node instanceof Name)
			{
				return $this->processName($node, $prefix, $legacyPrefixToNamespace->getNewNamespace(), $isNewFile);
			}

			return $this->processIdentifier($node, $prefix, $legacyPrefixToNamespace->getNewNamespace(), $isNewFile);
		}

		return null;
	}
}
