<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla6
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla6\Template;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\InlineHTML;
use Rector\Application\Provider\CurrentFileProvider;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Adds the `@var` annotation for `$this` to Joomla template files.
 *
 * `index.php`, `component.php`, `offline.php` and `error.php` are included from the document
 * object, so `$this` is the document there — but without an annotation neither an IDE nor PHPStan
 * can see that, which makes static analysis of a template pointless.
 *
 * The type map is verified against Joomla 6.1.1:
 *
 *   - `libraries/src/Document/ErrorDocument.php` declares `class ErrorDocument extends
 *     HtmlDocument` and sets `$params['file'] = 'error.php'`, so `error.php` is rendered by
 *     ErrorDocument — the more precise type than its HtmlDocument parent.
 *   - `libraries/src/Application/SiteApplication.php` sets `themeFile` to `offline.php` and
 *     `component.php`, both rendered through the HtmlDocument.
 *
 * A template folder is recognised by a `templateDetails.xml` next to the file. That is more
 * reliable than a path check on `templates/` and also works when the repository contains only
 * the template folder itself.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla6\Template\TemplateThisTypehintRector\TemplateThisTypehintRectorTest
 */
final class TemplateThisTypehintRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * Configuration key for additional variables Joomla provides in the template scope.
     */
    public const EXTRA_VARIABLES = 'extra_variables';

    /**
     * The document class per template file name.
     *
     * @var array<string, string>
     */
    private const DOCUMENT_TYPES = [
        'index.php'     => '\\Joomla\\CMS\\Document\\HtmlDocument',
        'component.php' => '\\Joomla\\CMS\\Document\\HtmlDocument',
        'offline.php'   => '\\Joomla\\CMS\\Document\\HtmlDocument',
        'error.php'     => '\\Joomla\\CMS\\Document\\ErrorDocument',
    ];

    /**
     * @var array<string, string>
     */
    private array $extraVariables = [];

    public function __construct(
        private readonly CurrentFileProvider $currentFileProvider,
    ) {
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public function configure(array $configuration): void
    {
        $extraVariables = $configuration[self::EXTRA_VARIABLES] ?? [];

        if (!\is_array($extraVariables)) {
            return;
        }

        $normalised = [];

        foreach ($extraVariables as $variableName => $type) {
            if (\is_string($variableName) && \is_string($type)) {
                $normalised[ltrim($variableName, '$')] = $type;
            }
        }

        $this->extraVariables = $normalised;
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [FileNode::class];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add the @var $this annotation to Joomla template files',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
<?php
\defined('_JEXEC') or die;

$app = Factory::getApplication();
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
<?php
/** @var \Joomla\CMS\Document\HtmlDocument $this */
\defined('_JEXEC') or die;

$app = Factory::getApplication();
CODE_SAMPLE,
                    [self::EXTRA_VARIABLES => []]
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

        $documentType = $this->resolveDocumentType($filePath);

        if ($documentType === null) {
            return null;
        }

        /** @var FileNode $node */
        return $this->addMissingAnnotations($node, $documentType);
    }

    // -------------------------------------------------------------------------

    /**
     * Returns the document class for the file, or null when it is not a template entry file.
     */
    private function resolveDocumentType(string $filePath): ?string
    {
        // The `.inc` suffix is allowed so the test fixtures match too.
        $fileName = preg_replace('/\.inc$/', '', basename($filePath));

        if (!\is_string($fileName) || !isset(self::DOCUMENT_TYPES[$fileName])) {
            return null;
        }

        // Layout overrides under html/ have a different $this and are out of scope. They are
        // excluded automatically, because they have no templateDetails.xml next to them.
        if (!is_file(\dirname($filePath) . '/templateDetails.xml')) {
            return null;
        }

        return self::DOCUMENT_TYPES[$fileName];
    }

    private function addMissingAnnotations(FileNode $node, string $documentType): ?FileNode
    {
        if ($node->stmts === []) {
            return null;
        }

        $targetStmt = $this->findFirstPhpStatement($node->stmts);

        if ($targetStmt === null) {
            return null;
        }

        $newDocs = [];

        if (!$this->hasVarAnnotation($node->stmts, 'this')) {
            $newDocs[] = new Doc('/** @var ' . $documentType . ' $this */');
        }

        foreach ($this->extraVariables as $variableName => $type) {
            if ($this->hasVarAnnotation($node->stmts, $variableName)) {
                continue;
            }

            $newDocs[] = new Doc('/** @var ' . $type . ' $' . $variableName . ' */');
        }

        if ($newDocs === []) {
            return null;
        }

        $existingComments = $targetStmt->getAttribute(AttributeKey::COMMENTS, []);
        $insertAt         = $this->findInsertPosition($existingComments);

        array_splice($existingComments, $insertAt, 0, $newDocs);
        $targetStmt->setAttribute(AttributeKey::COMMENTS, $existingComments);

        return $node;
    }

    /**
     * Inserts after a leading file-header docblock, otherwise at the very top.
     *
     * @param array<\PhpParser\Comment> $comments
     */
    private function findInsertPosition(array $comments): int
    {
        if ($comments === []) {
            return 0;
        }

        $first = $comments[0];

        if ($first instanceof Doc && $this->isFileHeaderDocblock($first->getText())) {
            return 1;
        }

        return 0;
    }

    private function isFileHeaderDocblock(string $text): bool
    {
        return str_contains($text, '@package')
            || str_contains($text, '@copyright')
            || str_contains($text, '@license');
    }

    /**
     * @param Stmt[] $stmts
     */
    private function hasVarAnnotation(array $stmts, string $variableName): bool
    {
        foreach ($stmts as $stmt) {
            foreach ($stmt->getAttribute(AttributeKey::COMMENTS, []) as $comment) {
                $text = $comment->getText();

                if (!str_contains($text, '@var')) {
                    continue;
                }

                if (preg_match('/@var\s+\S+\s+\$' . preg_quote($variableName, '/') . '\b/', $text) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param Stmt[] $stmts
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
