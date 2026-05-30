<?php
/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2022 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare (strict_types=1);

namespace Rector\Naming\Rector\FileWithoutNamespace;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * A Rector rule to namespace legacy Joomla 3 Helper classes into Joomla 4+ MVC namespaced classes
 *
 * @since  1.0.0
 * @see    \Rector\Tests\Naming\Rector\FileWithoutNamespace\JoomlaLegacyMVCToJ4Rector\JoomlaLegacyToNamespacedRectorTest
 */
final class JoomlaHelpersToJ4Rector extends JoomlaLegacyMVCToJ4Rector implements ConfigurableRectorInterface
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
abstract class HelloWorldHelper extends \Joomla\CMS\Helper\ContentHelper
{
}
CODE_SAMPLE
				, <<<'CODE_SAMPLE'
namespace Acme\Example\Administrator\Helper;

abstract class HelloworldHelper extends \Joomla\CMS\Helper\ContentHelper
{
}
CODE_SAMPLE
			),
		]);
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

		foreach ($this->legacyPrefixesToNamespaces as $legacyPrefixToNamespace)
		{
			$prefix = $legacyPrefixToNamespace->getNamespacePrefix();

			$matchesPrefix = str_starts_with($nodeName, $prefix . 'Helper')
				|| (str_starts_with($nodeName, $prefix) && str_ends_with($nodeName, 'Helper'));

			if (!$matchesPrefix)
			{
				continue;
			}

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
