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

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\InlineHTML;
use Rector\Application\Provider\CurrentFileProvider;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Adds a at-var ViewClass $this doc comment to Joomla view template files in tmpl directories.
 *
 * Scans all PHP files under any `tmpl/<viewname>/` directory and, if the
 * `@var ... $this` annotation is absent, prepends it to the first PHP statement.
 * The fully-qualified view class is resolved from `src/View/<viewname>/HtmlView.php`
 * relative to the component root (the directory that contains `tmpl/`).
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla5\ViewThisTypehintRector\ViewThisTypehintRectorTest
 */
final class ViewThisTypehintRector extends AbstractRector
{
    public function __construct(
        private readonly CurrentFileProvider $currentFileProvider,
    ) {
    }

    public function getNodeTypes(): array
    {
        return [FileNode::class];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add /** @var ViewClass $this */ doc comment to Joomla view template files found in tmpl directories',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
<?php
defined('_JEXEC') or die;
$items = $this->items;
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
<?php
/** @var \Acme\Component\Example\Site\View\Articles\HtmlView $this */
defined('_JEXEC') or die;
$items = $this->items;
CODE_SAMPLE
                ),
            ]
        );
    }

    public function refactor(Node $node): ?Node
    {
        $file = $this->currentFileProvider->getFile();

        if ($file === null) {
            return null;
        }

        $filePath = str_replace('\\', '/', $file->getFilePath());

        // Match files under tmpl/<viewname>/<anything>.php (also .php.inc for test fixtures)
        if (!preg_match('#/tmpl/([^/]+)/[^/]+\.php(?:\.inc)?$#i', $filePath, $viewMatches)) {
            return null;
        }

        $tmplViewFolder = $viewMatches[1];

        if (!preg_match('#^(.*)/tmpl/[^/]+/[^/]+\.php(?:\.inc)?$#i', $filePath, $rootMatches)) {
            return null;
        }

        $componentRoot = $rootMatches[1];
        $viewClassName = $this->resolveViewClassName($componentRoot, $tmplViewFolder);

        if ($viewClassName === null) {
            return null;
        }

        /** @var FileNode $node */
        return $this->addVarAnnotationIfMissing($node, $viewClassName);
    }

    // -------------------------------------------------------------------------

    /**
     * Locate src/View/<viewFolder>/HtmlView.php relative to $componentRoot and
     * return the fully-qualified class name including a leading backslash.
     */
    private function resolveViewClassName(string $componentRoot, string $tmplViewFolder): ?string
    {
        $viewDir = $componentRoot . '/src/View';

        if (!is_dir($viewDir)) {
            return null;
        }

        $entries = scandir($viewDir);

        if ($entries === false) {
            return null;
        }

        $matchingFolder = null;

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (!is_dir($viewDir . '/' . $entry)) {
                continue;
            }

            if (strcasecmp($entry, $tmplViewFolder) === 0) {
                $matchingFolder = $entry;
                break;
            }
        }

        if ($matchingFolder === null) {
            return null;
        }

        $htmlViewPath = $viewDir . '/' . $matchingFolder . '/HtmlView.php';

        if (!is_file($htmlViewPath)) {
            return null;
        }

        return $this->extractClassFqnFromFile($htmlViewPath);
    }

    /**
     * Extract the fully-qualified class name from a PHP file using regex.
     * Returns null when namespace or class declaration cannot be found.
     */
    private function extractClassFqnFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            return null;
        }

        if (!preg_match('/^namespace\s+([\w\\\\]+)\s*;/m', $content, $nsMatches)) {
            return null;
        }

        if (!preg_match('/^(?:final\s+|abstract\s+)?class\s+(\w+)/m', $content, $classMatches)) {
            return null;
        }

        return '\\' . $nsMatches[1] . '\\' . $classMatches[1];
    }

    /**
     * Prepend the @var doc comment to the first non-InlineHTML statement unless
     * a @var $this annotation already exists somewhere in the file.
     */
    private function addVarAnnotationIfMissing(FileNode $node, string $viewClassName): ?FileNode
    {
        if ($node->stmts === []) {
            return null;
        }

        if ($this->hasVarThisAnnotation($node->stmts)) {
            return null;
        }

        $targetStmt = $this->findFirstPhpStatement($node->stmts);

        if ($targetStmt === null) {
            return null;
        }

        $varDoc           = new Doc('/** @var ' . $viewClassName . ' $this */');
        $existingComments = $targetStmt->getAttribute(AttributeKey::COMMENTS, []);

        $targetStmt->setAttribute(AttributeKey::COMMENTS, array_merge([$varDoc], $existingComments));

        return $node;
    }

    /**
     * @param  Stmt[]  $stmts
     */
    private function hasVarThisAnnotation(array $stmts): bool
    {
        foreach ($stmts as $stmt) {
            $comments = $stmt->getAttribute(AttributeKey::COMMENTS, []);

            foreach ($comments as $comment) {
                $text = $comment->getText();

                if (str_contains($text, '@var') && str_contains($text, '$this')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns the first statement that is NOT an InlineHTML node, or null when
     * the file contains only HTML content.
     *
     * @param  Stmt[]  $stmts
     */
    private function findFirstPhpStatement(array $stmts): ?Stmt
    {
        foreach ($stmts as $stmt) {
            if (!$stmt instanceof InlineHTML) {
                return $stmt;
            }
        }

        return null;
    }
}
