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

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces Table::getInstance($type) static calls with direct instantiation.
 *
 * The replacement class FQN is resolved by first checking whether a component-specific
 * table class exists (requires `component_namespace` configuration and autoloadPaths),
 * then falling back to the core Joomla\CMS\Table\<Type> namespace.
 *
 * Only handles assignment expressions ($var = Table::getInstance('Type')) where
 * the type argument is a string literal and the optional second argument (prefix)
 * is the default 'JTable'.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla5\TableGetInstanceRector\TableGetInstanceRectorTest
 */
final class TableGetInstanceRector extends AbstractRector implements ConfigurableRectorInterface
{
    public const COMPONENT_NAMESPACE = 'component_namespace';

    private const TABLE_CLASS = 'Joomla\\CMS\\Table\\Table';

    private string $componentNamespace = '';

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function configure(array $configuration): void
    {
        $this->componentNamespace = $configuration[self::COMPONENT_NAMESPACE] ?? '';
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace Table::getInstance() with direct class instantiation',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
use Joomla\CMS\Table\Table;

$table = Table::getInstance('Content');
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
use Joomla\CMS\Table\Table;

$db = \Joomla\CMS\Factory::getDbo();
$table = new \Joomla\CMS\Table\Content($db);
CODE_SAMPLE,
                    [self::COMPONENT_NAMESPACE => 'Acme\\Component\\Example']
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    /**
     * @param Expression $node
     * @return Expression[]|null
     */
    public function refactor(Node $node): ?array
    {
        if (!$node->expr instanceof Assign) {
            return null;
        }

        $assign = $node->expr;

        if (!$assign->expr instanceof StaticCall) {
            return null;
        }

        $call = $assign->expr;

        if (!$this->isName($call->class, self::TABLE_CLASS)) {
            return null;
        }

        if (!$this->isName($call->name, 'getInstance')) {
            return null;
        }

        $tableType = $this->extractTableType($call);

        if ($tableType === null) {
            return null;
        }

        $fqn = $this->resolveTableFqn($tableType);

        $dbAssign = new Expression(
            new Assign(
                new \PhpParser\Node\Expr\Variable('db'),
                new StaticCall(
                    new Name\FullyQualified('Joomla\\CMS\\Factory'),
                    'getDbo',
                    []
                )
            )
        );

        $tableAssign = new Expression(
            new Assign(
                $assign->var,
                new New_(
                    new Name\FullyQualified($fqn),
                    [new Arg(new \PhpParser\Node\Expr\Variable('db'))]
                )
            )
        );

        return [$dbAssign, $tableAssign];
    }

    private function extractTableType(StaticCall $call): ?string
    {
        if ($call->args === []) {
            return null;
        }

        $firstArg = $call->args[0];

        if (!$firstArg instanceof Arg || !$firstArg->value instanceof String_) {
            return null;
        }

        // If a second argument (prefix) is given, only accept the default 'JTable'
        if (count($call->args) >= 2) {
            $secondArg = $call->args[1];

            if (!$secondArg instanceof Arg || !$secondArg->value instanceof String_) {
                return null;
            }

            if ($secondArg->value->value !== 'JTable') {
                return null;
            }
        }

        return $firstArg->value->value;
    }

    private function resolveTableFqn(string $type): string
    {
        if ($this->componentNamespace !== '') {
            $componentClass = $this->componentNamespace . '\\Administrator\\Table\\' . $type . 'Table';

            if ($this->reflectionProvider->hasClass($componentClass)) {
                return $componentClass;
            }
        }

        return 'Joomla\\CMS\\Table\\' . $type;
    }
}