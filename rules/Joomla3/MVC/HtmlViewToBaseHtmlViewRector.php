<?php

/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2026 Nicholas K. Dionysopoulos
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla3\MVC;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Adds `as BaseHtmlView` to the import of Joomla\CMS\MVC\View\HtmlView and
 * updates the extends clause accordingly.
 *
 * Handles both the short-name form (via an existing use statement) and the
 * fully-qualified form (adds a new use statement before the class).
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla3\MVC\HtmlViewToBaseHtmlViewRector\HtmlViewToBaseHtmlViewRectorTest
 */
final class HtmlViewToBaseHtmlViewRector extends AbstractRector
{
    private const HTML_VIEW_FQN = 'Joomla\CMS\MVC\View\HtmlView';

    /**
     * @return  array<class-string<Node>>
     * @since   1.0.0
     */
    public function getNodeTypes(): array
    {
        return [Namespace_::class];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Import Joomla\CMS\MVC\View\HtmlView with alias BaseHtmlView in classes that extend it directly',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Joomla\CMS\MVC\View\HtmlView;

class DefaultView extends HtmlView
{
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class DefaultView extends BaseHtmlView
{
}
CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * @since  1.0.0
     */
    public function refactor(Node $node): ?Node
    {
        /** @var Namespace_ $node */
        $existingUseItem = $this->findHtmlViewUseItem($node->stmts);

        // Already transformed: use statement already carries the BaseHtmlView alias
        if ($existingUseItem instanceof UseItem
            && $existingUseItem->alias instanceof Identifier
            && $existingUseItem->alias->name === 'BaseHtmlView') {
            return null;
        }

        // Find a class that extends HtmlView (resolved via use statement or FQN)
        $targetClass = $this->findExtendingClass($node->stmts);

        if ($targetClass === null) {
            return null;
        }

        if ($existingUseItem !== null) {
            // Add or update the alias on the existing use item
            $existingUseItem->alias = new Identifier('BaseHtmlView');
        } else {
            // No use statement exists (FQN extends) — insert one before the class
            $newUse = new Use_([
                new UseItem(
                    new Name(self::HTML_VIEW_FQN),
                    new Identifier('BaseHtmlView')
                )
            ]);

            $insertIndex = $this->findUseInsertionIndex($node->stmts);
            $node->stmts = array_merge(
                array_slice($node->stmts, 0, $insertIndex),
                [$newUse],
                array_slice($node->stmts, $insertIndex)
            );
        }

        // Update extends clause to use the alias name
        $targetClass->extends = new Name('BaseHtmlView');

        return $node;
    }

    // -------------------------------------------------------------------------

    /**
     * Returns the first class in $stmts that directly extends HtmlView (FQN).
     *
     * @param  array<Node>  $stmts
     * @since  1.0.0
     */
    private function findExtendingClass(array $stmts): ?Class_
    {
        foreach ($stmts as $stmt) {
            if (!$stmt instanceof Class_) {
                continue;
            }

            if ($stmt->extends === null) {
                continue;
            }

            if ($this->getName($stmt->extends) === self::HTML_VIEW_FQN) {
                return $stmt;
            }
        }

        return null;
    }

    /**
     * Returns the UseItem for Joomla\CMS\MVC\View\HtmlView, or null if absent.
     *
     * @param  array<Node>  $stmts
     * @since  1.0.0
     */
    private function findHtmlViewUseItem(array $stmts): ?UseItem
    {
        foreach ($stmts as $stmt) {
            if (!$stmt instanceof Use_) {
                continue;
            }

            foreach ($stmt->uses as $useItem) {
                if ($useItem->name->toString() === self::HTML_VIEW_FQN) {
                    return $useItem;
                }
            }
        }

        return null;
    }

    /**
     * Returns the stmts index at which a new use statement should be inserted.
     * Inserts after the last existing use statement, or at 0 when none exist.
     *
     * @param  array<Node>  $stmts
     * @since  1.0.0
     */
    private function findUseInsertionIndex(array $stmts): int
    {
        $insertAfter = 0;

        foreach ($stmts as $i => $stmt) {
            if ($stmt instanceof Use_) {
                $insertAfter = $i + 1;
            } elseif ($stmt instanceof Class_) {
                break;
            }
        }

        return $insertAfter;
    }
}
