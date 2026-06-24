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
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Registers a rename for every PHP file found under views/<view>/tmpl/ so that
 * it ends up under tmpl/<view>/ after running the generated rename.php script.
 *
 * Source:  <side>/views/<view>/tmpl/<file>.php
 * Target:  <side>/tmpl/<view>/<file>.php
 *
 * The rule does not modify any PHP AST – it only populates the
 * FileRenameCollectorService which writes rename.php to the project root.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla3\MVC\ViewsTmplMoveRector\ViewsTmplMoveRectorTest
 */
final class ViewsTmplMoveRector extends AbstractRector
{
    use JoomlaNamespaceHandlingTrait;

    /**
     * @since  1.0.0
     */
    public function __construct(
        protected readonly FileRenameCollectorService $fileRenameCollectorService
    ) {
    }

    /**
     * @return  array<class-string<Node>>
     * @since   1.0.0
     */
    public function getNodeTypes(): array
    {
        return [FileNode::class];
    }

    /**
     * @since  1.0.0
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Move template layout files from views/<view>/tmpl/ to tmpl/<view>/ (Joomla 3 → 4 structure).',
            [
                new CodeSample(
                    '// File: admin/views/example/tmpl/default.php',
                    '// File: admin/tmpl/example/default.php'
                ),
            ]
        );
    }

    /**
     * Detects files inside views/<view>/tmpl/ and registers them for relocation.
     *
     * @param   FileNode  $node
     *
     * @since   1.0.0
     */
    public function refactor(Node $node): ?Node
    {
        $filePath = str_replace('\\', '/', $this->getFile()->getFilePath());

        if (!preg_match('#/views/([^/]+)/tmpl/#', $filePath)) {
            return null;
        }

        $newPath = (string) preg_replace('#/views/([^/]+)/tmpl/#', '/tmpl/$1/', $filePath);

        if ($newPath === $filePath) {
            return null;
        }

        $projectRoot = $this->divineProjectRootFolder();

        if ($projectRoot === null) {
            return null;
        }

        $this->fileRenameCollectorService->addRename($projectRoot, $filePath, $newPath);

        return null;
    }
}
