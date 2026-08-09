<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla6
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla6\Module;

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
 * Adds `@var` annotations for the standard module layout variables to files under
 * `mod_<name>/tmpl/`.
 *
 * Module layouts are included from the dispatcher at runtime, so static analysis only sees
 * undefined variables. The annotations make a module analysable at all.
 *
 * The variable set is taken from `AbstractModuleDispatcher::getLayoutData()` in Joomla 6.1.1
 * (libraries/src/Dispatcher/AbstractModuleDispatcher.php), which returns exactly
 * `module`, `app`, `input`, `params` and `template`. The types come from the properties of
 * `Joomla\CMS\Dispatcher\Dispatcher` (`$app`, `$input`) and from the values built there.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla6\Module\ModuleTmplTypehintRector\ModuleTmplTypehintRectorTest
 */
final class ModuleTmplTypehintRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * Configuration key for additional layout variables, as `name => type`.
     */
    public const EXTRA_VARIABLES = 'extra_variables';

    /**
     * The variables every module layout receives, in the order getLayoutData() builds them.
     *
     * @var array<string, string>
     */
    private const DEFAULT_VARIABLES = [
        'module'   => '\\stdClass',
        'app'      => '\\Joomla\\CMS\\Application\\CMSApplicationInterface',
        'input'    => '\\Joomla\\Input\\Input',
        'params'   => '\\Joomla\\Registry\\Registry',
        'template' => 'string',
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
            'Add @var annotations for the standard layout variables to module template files',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
<?php
\defined('_JEXEC') or die;

foreach ($items as $item) {
    echo $item->title;
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
<?php
/** @var \stdClass $module */
/** @var \Joomla\CMS\Application\CMSApplicationInterface $app */
/** @var \Joomla\Input\Input $input */
/** @var \Joomla\Registry\Registry $params */
/** @var string $template */
/** @var \stdClass[] $items */
\defined('_JEXEC') or die;

foreach ($items as $item) {
    echo $item->title;
}
CODE_SAMPLE,
                    [self::EXTRA_VARIABLES => ['items' => '\\stdClass[]']]
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

        if (!$this->isModuleLayoutFile($filePath)) {
            return null;
        }

        /** @var FileNode $node */
        return $this->addMissingAnnotations($node);
    }

    // -------------------------------------------------------------------------

    /**
     * A module layout lives under `mod_<name>/tmpl/`. Component layouts also use `tmpl/`,
     * which is why the `mod_` prefixed folder is required rather than just `tmpl/`.
     */
    private function isModuleLayoutFile(string $filePath): bool
    {
        // The `.inc` suffix is allowed so the test fixtures match too.
        if (preg_match('#/(mod_[^/]+)/tmpl/.+\.php(?:\.inc)?$#i', $filePath) === 1) {
            return true;
        }

        // Fallback: a tmpl/ folder whose parent contains a mod_<name>.xml manifest.
        if (preg_match('#^(.*)/tmpl/.+\.php(?:\.inc)?$#i', $filePath, $matches) !== 1) {
            return false;
        }

        return glob($matches[1] . '/mod_*.xml') !== [];
    }

    private function addMissingAnnotations(FileNode $node): ?FileNode
    {
        if ($node->stmts === []) {
            return null;
        }

        $targetStmt = $this->findFirstPhpStatement($node->stmts);

        if ($targetStmt === null) {
            return null;
        }

        // Extra variables are appended, so a configured type never displaces a standard one.
        $variables = self::DEFAULT_VARIABLES;

        foreach ($this->extraVariables as $variableName => $type) {
            $variables[$variableName] = $type;
        }

        $newDocs = [];

        foreach ($variables as $variableName => $type) {
            // Each variable is checked on its own, so partially annotated files are completed.
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
     * Returns the index at which the annotations should be inserted: after a leading
     * file-header docblock, otherwise at the very top.
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
