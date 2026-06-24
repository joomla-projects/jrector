<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla6
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla6;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\UnionType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces CMSObject with stdClass in:
 *   - Return type hints (native PHP types: simple, nullable, union, intersection)
 *   - @return tags in PHPDoc comments
 *   - Property type hints (simple, nullable, union, intersection)
 *   - @var tags in property PHPDoc comments
 *
 * CMSObject was removed in Joomla 6. The type stdClass is its direct replacement
 * because CMSObject was effectively a thin wrapper around stdClass.
 *
 * Matches:
 *   CMSObject                      — short name
 *   \CMSObject                     — globally-qualified short name
 *   \Joomla\CMS\Object\CMSObject   — fully-qualified name
 *   Joomla\CMS\Object\CMSObject    — FQN without leading backslash
 *
 * @param, @property and parameter type hints are intentionally left untouched.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla6\CmsObjectReturnTypeRector\CmsObjectReturnTypeRectorTest
 */
final class CmsObjectReturnTypeRector extends AbstractRector
{
    private const CMSOBJ_SHORT = 'CMSObject';
    private const CMSOBJ_FQN  = 'Joomla\CMS\Object\CMSObject';

    public function getNodeTypes(): array
    {
        return [ClassMethod::class, Function_::class, Property::class];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace CMSObject with stdClass in return type hints and @return PHPDoc tags (Joomla 6)',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
/**
 * @return CMSObject
 */
public function getItem(): CMSObject
{
    return new CMSObject();
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
/**
 * @return stdClass
 */
public function getItem(): stdClass
{
    return new CMSObject();
}
CODE_SAMPLE
                ),
            ]
        );
    }

    public function refactor(Node $node): ?Node
    {
        /** @var ClassMethod|Function_|Property $node */
        $hasChanged = false;

        if ($node instanceof Property) {
            // 1a. Property type hint
            if ($node->type !== null) {
                $newType = $this->replaceTypeNode($node->type);

                if ($newType !== null) {
                    $node->type = $newType;
                    $hasChanged = true;
                }
            }

            // 1b. @var tag in property PHPDoc
            $docComment = $node->getDocComment();

            if ($docComment !== null) {
                $newDocText = $this->replaceDocTag($docComment->getText(), '@var');

                if ($newDocText !== $docComment->getText()) {
                    $node->setDocComment(new Doc($newDocText));
                    $hasChanged = true;
                }
            }
        } else {
            // 2a. Native return type hint (including nullable, union, intersection)
            if ($node->returnType !== null) {
                $newType = $this->replaceTypeNode($node->returnType);

                if ($newType !== null) {
                    $node->returnType = $newType;
                    $hasChanged       = true;
                }
            }

            // 2b. @return tag inside the PHPDoc comment
            $docComment = $node->getDocComment();

            if ($docComment !== null) {
                $newDocText = $this->replaceDocTag($docComment->getText(), '@return');

                if ($newDocText !== $docComment->getText()) {
                    $node->setDocComment(new Doc($newDocText));
                    $hasChanged = true;
                }
            }
        }

        return $hasChanged ? $node : null;
    }

    // -------------------------------------------------------------------------
    // Type-node helpers
    // -------------------------------------------------------------------------

    /**
     * Recursively replace CMSObject with stdClass inside a type node.
     * Returns the modified node or null when nothing changed.
     */
    private function replaceTypeNode(Node $type): ?Node
    {
        if ($type instanceof Name && $this->isCmsObjectName($type)) {
            return new Name('stdClass');
        }

        if ($type instanceof NullableType) {
            $inner = $this->replaceTypeNode($type->type);

            if ($inner !== null) {
                $type->type = $inner;
                return $type;
            }
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            $changed = false;

            foreach ($type->types as $idx => $t) {
                $replacement = $this->replaceTypeNode($t);

                if ($replacement !== null) {
                    $type->types[$idx] = $replacement;
                    $changed           = true;
                }
            }

            return $changed ? $type : null;
        }

        return null;
    }

    /**
     * Returns true when the Name node refers to CMSObject in any of its forms.
     */
    private function isCmsObjectName(Name $name): bool
    {
        $str = $name->toString();

        return $str === self::CMSOBJ_SHORT || $str === self::CMSOBJ_FQN;
    }

    // -------------------------------------------------------------------------
    // PHPDoc helper
    // -------------------------------------------------------------------------

    /**
     * Replace CMSObject with stdClass on every line that contains the given PHPDoc tag.
     * All name forms are handled:
     *   CMSObject, \CMSObject, Joomla\CMS\Object\CMSObject, \Joomla\CMS\Object\CMSObject
     */
    private function replaceDocTag(string $docText, string $tag): string
    {
        $lines   = explode("\n", $docText);
        $changed = false;

        foreach ($lines as &$line) {
            if (!str_contains($line, $tag)) {
                continue;
            }

            $newLine = (string) preg_replace(
                '/\\\\?(?:Joomla\\\\CMS\\\\Object\\\\)?CMSObject\b/',
                'stdClass',
                $line
            );

            if ($newLine !== $line) {
                $line    = $newLine;
                $changed = true;
            }
        }

        unset($line);

        return $changed ? implode("\n", $lines) : $docText;
    }
}
