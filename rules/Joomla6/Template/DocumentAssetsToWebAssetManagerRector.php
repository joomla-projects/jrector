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

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeVisitor;
use PHPStan\Type\ObjectType;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Moves direct CSS/JS registration from the document onto the WebAssetManager.
 *
 * The WebAssetManager is the intended way to manage assets with dependencies, versioning and
 * attributes. The old calls bypass the asset registry completely, so ordering and deduplication
 * cannot be controlled.
 *
 * Verified against Joomla 6.1.1:
 *
 *   - `libraries/src/Document/Document.php` marks `addScript()`, `addScriptDeclaration()`,
 *     `addStyleSheet()` and `addStyleDeclaration()` as `@deprecated 4.3 will be removed in 7.0`.
 *     `addScriptOptions()` carries no deprecation and is therefore left alone.
 *   - `libraries/src/WebAsset/WebAssetManager.php` resolves `registerAndUseStyle()`,
 *     `registerAndUseScript()`, `addInlineStyle()` and `addInlineScript()` through `__call()`,
 *     onto `registerAsset(string $type, $asset, string $uri = '', array $options = [], ...)`
 *     and `addInline(string $type, $content, ...)`. Hence the argument order
 *     `registerAndUseStyle($name, $uri)`.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla6\Template\DocumentAssetsToWebAssetManagerRector\DocumentAssetsToWebAssetManagerRectorTest
 */
final class DocumentAssetsToWebAssetManagerRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * Configuration key for a prefix put in front of every derived asset name.
     */
    public const ASSET_NAME_PREFIX = 'asset_name_prefix';

    private const DOCUMENT_TYPE = 'Joomla\\CMS\\Document\\Document';

    private const WA_VARIABLE = 'wa';

    /**
     * Document method => WebAssetManager method, for the calls that take a URL.
     *
     * @var array<string, string>
     */
    private const REGISTER_METHODS = [
        'addStyleSheet' => 'registerAndUseStyle',
        'addScript'     => 'registerAndUseScript',
    ];

    /**
     * Document method => WebAssetManager method, for the calls that take inline content.
     *
     * @var array<string, string>
     */
    private const INLINE_METHODS = [
        'addStyleDeclaration'  => 'addInlineStyle',
        'addScriptDeclaration' => 'addInlineScript',
    ];

    /**
     * HTMLHelper key => WebAssetManager method.
     *
     * @var array<string, string>
     */
    private const HTML_HELPER_METHODS = [
        'stylesheet' => 'registerAndUseStyle',
        'script'     => 'registerAndUseScript',
    ];

    private string $assetNamePrefix = '';

    /**
     * @param array<string, mixed> $configuration
     */
    public function configure(array $configuration): void
    {
        $prefix = $configuration[self::ASSET_NAME_PREFIX] ?? '';

        if (\is_string($prefix)) {
            $this->assetNamePrefix = $prefix;
        }
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace direct document asset calls with the WebAssetManager',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
$doc = $this->getDocument();
$doc->addStyleSheet('media/templates/site/foo/css/template.css');
$doc->addScriptDeclaration('console.log("hi");');
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
$doc = $this->getDocument();
$wa = $doc->getWebAssetManager();
$wa->registerAndUseStyle('foo.template', 'media/templates/site/foo/css/template.css');
$wa->addInlineScript('console.log("hi");');
CODE_SAMPLE,
                    [self::ASSET_NAME_PREFIX => '']
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Class_::class, FileNode::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Class_) {
            if ($node->isAnonymous()) {
                return null;
            }

            $hasChanged = false;

            foreach ($node->getMethods() as $method) {
                if ($method->stmts !== null && $this->refactorScope($method->stmts, false)) {
                    $method->stmts = $this->pendingStmts;
                    $hasChanged    = true;
                }
            }

            return $hasChanged ? $node : null;
        }

        /** @var FileNode $node */
        if (!$this->refactorScope($node->stmts, true)) {
            return null;
        }

        $node->stmts = $this->pendingStmts;

        return $node;
    }

    // -------------------------------------------------------------------------

    /**
     * Holds the rewritten statement list of the scope that was processed last.
     *
     * @var Stmt[]
     */
    private array $pendingStmts = [];

    /**
     * Rewrites one scope — a method body or the top level of a file.
     *
     * @param Stmt[] $stmts
     */
    private function refactorScope(array $stmts, bool $isFileScope): bool
    {
        $documentVariables = $this->collectDocumentVariables($stmts);
        $waSource          = null;
        $hasChanged        = false;

        $callback = function (Node $node) use ($documentVariables, $isFileScope, &$waSource, &$hasChanged) {
            // Nested classes and functions have their own scope.
            if ($node instanceof Class_ || $node instanceof Node\FunctionLike) {
                return NodeVisitor::DONT_TRAVERSE_CHILDREN;
            }

            $replacement = $this->refactorCall($node, $documentVariables, $isFileScope, $waSource);

            if ($replacement === null) {
                return null;
            }

            $hasChanged = true;

            return $replacement;
        };

        $this->traverseNodesWithCallable($stmts, $callback);

        if (!$hasChanged) {
            return false;
        }

        $this->pendingStmts = $stmts;

        // The manager is fetched once per scope, right before the first rewritten call.
        if ($waSource !== null && !$this->hasWaAssignment($stmts)) {
            $insertIndex = $this->findFirstWaUsageIndex($stmts);

            if ($insertIndex !== null) {
                $assignment = new Expression(new Assign(new Variable(self::WA_VARIABLE), $waSource));
                array_splice($this->pendingStmts, $insertIndex, 0, [$assignment]);
            }
        }

        return true;
    }

    /**
     * Rewrites a single call, remembering how the WebAssetManager has to be obtained.
     */
    private function refactorCall(Node $node, array $documentVariables, bool $isFileScope, ?Expr &$waSource): ?Expr
    {
        if ($node instanceof MethodCall) {
            return $this->refactorDocumentCall($node, $documentVariables, $isFileScope, $waSource);
        }

        if ($node instanceof StaticCall) {
            return $this->refactorHtmlHelperCall($node, $documentVariables, $isFileScope, $waSource);
        }

        return null;
    }

    /**
     * @param string[] $documentVariables
     */
    private function refactorDocumentCall(MethodCall $call, array $documentVariables, bool $isFileScope, ?Expr &$waSource): ?Expr
    {
        if (!$call->name instanceof Node\Identifier) {
            return null;
        }

        $methodName = $call->name->toString();

        $isRegister = isset(self::REGISTER_METHODS[$methodName]);
        $isInline   = isset(self::INLINE_METHODS[$methodName]);

        // addScriptOptions() is not deprecated and stays as it is.
        if (!$isRegister && !$isInline) {
            return null;
        }

        if (!$this->isDocumentReceiver($call->var, $documentVariables, $isFileScope)) {
            return null;
        }

        $firstArg = $call->args[0] ?? null;

        if (!$firstArg instanceof Arg) {
            return null;
        }

        if ($isInline) {
            $waSource ??= $this->buildWaSource($call->var, $isFileScope);

            return new MethodCall(
                new Variable(self::WA_VARIABLE),
                self::INLINE_METHODS[$methodName],
                [$firstArg]
            );
        }

        // A generated name from a dynamic path is not reliable.
        if (!$firstArg->value instanceof String_) {
            return null;
        }

        $waSource ??= $this->buildWaSource($call->var, $isFileScope);

        return new MethodCall(
            new Variable(self::WA_VARIABLE),
            self::REGISTER_METHODS[$methodName],
            [
                new Arg(new String_($this->deriveAssetName($firstArg->value->value))),
                $firstArg,
            ]
        );
    }

    /**
     * @param string[] $documentVariables
     */
    private function refactorHtmlHelperCall(StaticCall $call, array $documentVariables, bool $isFileScope, ?Expr &$waSource): ?Expr
    {
        if (!$call->class instanceof Node\Name || $call->class->getLast() !== 'HTMLHelper') {
            return null;
        }

        if (!$this->isName($call->name, '_')) {
            return null;
        }

        $typeArg = $call->args[0] ?? null;

        if (!$typeArg instanceof Arg || !$typeArg->value instanceof String_) {
            return null;
        }

        $key = strtolower($typeArg->value->value);

        // behavior.* and bootstrap.* need useScript() with the correct core asset names and a
        // mapping table of their own — deliberately out of scope.
        if (!isset(self::HTML_HELPER_METHODS[$key])) {
            return null;
        }

        // Only the plain two-argument form is converted. The option arrays of HTMLHelper and of
        // the WebAssetManager do not mean the same thing, so passing them through would be a
        // guess.
        if (\count($call->args) !== 2) {
            return null;
        }

        $fileArg = $call->args[1];

        if (!$fileArg instanceof Arg || !$fileArg->value instanceof String_) {
            return null;
        }

        $waSource ??= $this->buildWaSourceWithoutReceiver($documentVariables, $isFileScope);

        return new MethodCall(
            new Variable(self::WA_VARIABLE),
            self::HTML_HELPER_METHODS[$key],
            [
                new Arg(new String_($this->deriveAssetName($fileArg->value->value))),
                $fileArg,
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Asset names
    // -------------------------------------------------------------------------

    /**
     * Derives the asset name from the file path.
     *
     * `media/templates/site/foo/css/template.css` becomes `foo.template`, and anything that does
     * not match a known layout falls back to the file name without extension. Everything is
     * lower cased and reduced to `[a-z0-9._-]`.
     */
    private function deriveAssetName(string $path): string
    {
        $normalised = str_replace('\\', '/', $path);

        // Drop a query string or fragment before looking at the file name.
        $normalised = (string) preg_split('/[?#]/', $normalised)[0];

        $fileName = $this->sanitise(pathinfo($normalised, \PATHINFO_FILENAME));

        $extensionName = null;

        if (preg_match('#(?:^|/)media/templates/[^/]+/([^/]+)/#', $normalised, $matches) === 1) {
            $extensionName = $matches[1];
        } elseif (preg_match('#(?:^|/)templates/([^/]+)/#', $normalised, $matches) === 1) {
            $extensionName = $matches[1];
        } elseif (preg_match('#(?:^|/)media/([^/]+)/#', $normalised, $matches) === 1) {
            $extensionName = $matches[1];
        }

        $name = $extensionName === null
            ? $fileName
            : $this->sanitise($extensionName) . '.' . $fileName;

        return $this->assetNamePrefix . $name;
    }

    private function sanitise(string $value): string
    {
        $value = strtolower($value);

        return (string) preg_replace('/[^a-z0-9._-]+/', '-', $value);
    }

    // -------------------------------------------------------------------------
    // Scope analysis
    // -------------------------------------------------------------------------

    /**
     * Collects variables that hold a document, e.g. `$doc = $this->getDocument();`.
     *
     * @param Stmt[] $stmts
     *
     * @return string[]
     */
    private function collectDocumentVariables(array $stmts): array
    {
        $variableNames = [];

        $this->traverseNodesWithCallable($stmts, function (Node $node) use (&$variableNames): ?Node {
            if (!$node instanceof Assign || !$node->var instanceof Variable || !\is_string($node->var->name)) {
                return null;
            }

            $expr = $node->expr;

            $isGetDocument = ($expr instanceof MethodCall || $expr instanceof StaticCall)
                && $expr->name instanceof Node\Identifier
                && $expr->name->toString() === 'getDocument';

            if ($isGetDocument) {
                $variableNames[] = $node->var->name;
            }

            return null;
        });

        return $variableNames;
    }

    /**
     * @param string[] $documentVariables
     */
    private function isDocumentReceiver(Expr $receiver, array $documentVariables, bool $isFileScope): bool
    {
        if ($receiver instanceof Variable && \is_string($receiver->name)) {
            // In a template file `$this` is the document itself.
            if ($receiver->name === 'this') {
                return $isFileScope || $this->resolvesToDocument($receiver);
            }

            if (\in_array($receiver->name, $documentVariables, true)) {
                return true;
            }
        }

        return $this->resolvesToDocument($receiver);
    }

    private function resolvesToDocument(Expr $expr): bool
    {
        $classNames = $this->getType($expr)->getObjectClassNames();

        if ($classNames === []) {
            return false;
        }

        $documentType = new ObjectType(self::DOCUMENT_TYPE);

        foreach ($classNames as $className) {
            if ($documentType->isSuperTypeOf(new ObjectType($className))->yes()) {
                return true;
            }
        }

        return false;
    }

    private function buildWaSource(Expr $receiver, bool $isFileScope): Expr
    {
        // A template file: $this is the document, so it offers getWebAssetManager() directly.
        if ($isFileScope && $receiver instanceof Variable && $receiver->name === 'this') {
            return new MethodCall(new Variable('this'), 'getWebAssetManager');
        }

        return new MethodCall($receiver, 'getWebAssetManager');
    }

    /**
     * @param string[] $documentVariables
     */
    private function buildWaSourceWithoutReceiver(array $documentVariables, bool $isFileScope): Expr
    {
        if ($documentVariables !== []) {
            return new MethodCall(new Variable($documentVariables[0]), 'getWebAssetManager');
        }

        if ($isFileScope) {
            return new MethodCall(new Variable('this'), 'getWebAssetManager');
        }

        return new MethodCall(
            new MethodCall(new Variable('this'), 'getDocument'),
            'getWebAssetManager'
        );
    }

    /**
     * @param Stmt[] $stmts
     */
    private function hasWaAssignment(array $stmts): bool
    {
        $hasAssignment = false;

        $this->traverseNodesWithCallable($stmts, static function (Node $node) use (&$hasAssignment): ?Node {
            if (
                $node instanceof Assign
                && $node->var instanceof Variable
                && $node->var->name === self::WA_VARIABLE
            ) {
                $hasAssignment = true;
            }

            return null;
        });

        return $hasAssignment;
    }

    /**
     * Index of the first statement that uses `$wa`, i.e. where the assignment has to go.
     *
     * @param Stmt[] $stmts
     */
    private function findFirstWaUsageIndex(array $stmts): ?int
    {
        foreach ($stmts as $index => $stmt) {
            $usesWa = false;

            $this->traverseNodesWithCallable($stmt, static function (Node $node) use (&$usesWa): ?Node {
                if ($node instanceof Variable && $node->name === self::WA_VARIABLE) {
                    $usesWa = true;
                }

                return null;
            });

            if ($usesWa) {
                return $index;
            }
        }

        return null;
    }
}
