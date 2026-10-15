<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla7
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla7;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitor;
use PHPStan\Reflection\ReflectionProvider;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces the static `getInstance()` accessors that Joomla 7 removes with the application or
 * the dependency injection container, the replacements their deprecation notices named.
 *
 *   Pathway::getInstance($client)             => Factory::getApplication()->getPathway()
 *   Session::getInstance()                    => Factory::getApplication()->getSession()
 *   Cache::getInstance($type, $options)       => Factory::getContainer()
 *   CacheController::getInstance($type, ...)       ->get(CacheControllerFactoryInterface::class)
 *                                                  ->createCacheController($type, $options)
 *
 * Inside a class that has `getApplication()` (a plugin, or anything reflection can confirm) the
 * application is taken from `$this->getApplication()` instead of the Factory, except in static
 * methods. The old `JPathway`, `JSession`, `JCache` and `JCacheController` aliases are covered.
 *
 * `Pathway::getInstance()` took the client name; the replacement is the pathway of the running
 * application, so the argument is dropped. `CacheController::addIncludePath()` has no
 * replacement and is left alone.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla7\GetInstanceToContainerRector\GetInstanceToContainerRectorTest
 */
final class GetInstanceToContainerRector extends AbstractRector
{
    private const FACTORY = 'Joomla\\CMS\\Factory';

    private const CACHE_FACTORY_INTERFACE = 'Joomla\\CMS\\Cache\\CacheControllerFactoryInterface';

    private const PLUGIN_ANCESTOR = 'Joomla\\CMS\\Plugin\\CMSPlugin';

    /**
     * Removed accessors whose object the running application provides: class => application getter.
     *
     * @var array<string, string>
     */
    private const APPLICATION_GETTERS = [
        'Joomla\\CMS\\Pathway\\Pathway' => 'getPathway',
        'JPathway'                      => 'getPathway',
        'Joomla\\CMS\\Session\\Session' => 'getSession',
        'JSession'                      => 'getSession',
    ];

    /**
     * Removed accessors replaced by the cache controller factory.
     *
     * @var string[]
     */
    private const CACHE_CLASSES = [
        'Joomla\\CMS\\Cache\\Cache',
        'JCache',
        'Joomla\\CMS\\Cache\\CacheController',
        'JCacheController',
    ];

    /**
     * The pre-namespace aliases are global class names, matched by their short name.
     *
     * @var string[]
     */
    private const LEGACY_ALIASES = ['JPathway', 'JSession', 'JCache', 'JCacheController'];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace the static getInstance() accessors removed in Joomla 7 with the application or the container',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
$session = Session::getInstance();
$pathway = Pathway::getInstance('site');
$cache   = Cache::getInstance('output', $options);
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
$session = \Joomla\CMS\Factory::getApplication()->getSession();
$pathway = \Joomla\CMS\Factory::getApplication()->getPathway();
$cache   = \Joomla\CMS\Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class)->createCacheController('output', $options);
CODE_SAMPLE
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
            return $this->refactorClass($node);
        }

        /** @var FileNode $node */
        return $this->refactorFileScope($node);
    }

    // -------------------------------------------------------------------------

    private function refactorClass(Class_ $class): ?Class_
    {
        if ($class->isAnonymous()) {
            return null;
        }

        $hasApplicationGetter = $this->hasApplicationGetter($class);
        $hasChanged           = false;

        foreach ($class->getMethods() as $method) {
            if ($method->stmts === null) {
                continue;
            }

            // There is no $this in a static method: fall back to the Factory there.
            if ($this->replaceCalls($method, $hasApplicationGetter && !$method->isStatic())) {
                $hasChanged = true;
            }
        }

        return $hasChanged ? $class : null;
    }

    /**
     * Calls outside any class, e.g. in a template file or a legacy helper script.
     */
    private function refactorFileScope(FileNode $fileNode): ?FileNode
    {
        $hasChanged = false;

        $this->traverseNodesWithCallable($fileNode->stmts, function (Node $node) use (&$hasChanged) {
            // Class bodies are handled by refactorClass().
            if ($node instanceof Class_) {
                return NodeVisitor::DONT_TRAVERSE_CHILDREN;
            }

            $replacement = $this->replacementFor($node, false);

            if ($replacement === null) {
                return null;
            }

            $hasChanged = true;

            return $replacement;
        });

        return $hasChanged ? $fileNode : null;
    }

    private function replaceCalls(ClassMethod $method, bool $useThis): bool
    {
        $hasChanged = false;

        $this->traverseNodesWithCallable($method, function (Node $node) use ($useThis, &$hasChanged) {
            $replacement = $this->replacementFor($node, $useThis);

            if ($replacement === null) {
                return null;
            }

            $hasChanged = true;

            return $replacement;
        });

        return $hasChanged;
    }

    /**
     * The expression that replaces a removed getInstance() call, or null when the node is something else.
     */
    private function replacementFor(Node $node, bool $useThis): ?Expr
    {
        if (!$node instanceof StaticCall || !$node->class instanceof Name) {
            return null;
        }

        if (!$this->isName($node->name, 'getInstance')) {
            return null;
        }

        $className = $this->removedClassName($node->class);

        if ($className === null) {
            return null;
        }

        if (isset(self::APPLICATION_GETTERS[$className])) {
            $application = $useThis
                ? new MethodCall(new Variable('this'), 'getApplication')
                : new StaticCall(new FullyQualified(self::FACTORY), 'getApplication');

            // Pathway::getInstance($client) named a client; the running application's pathway replaces it.
            return new MethodCall($application, self::APPLICATION_GETTERS[$className]);
        }

        if (\in_array($className, self::CACHE_CLASSES, true)) {
            $factory = new MethodCall(
                new StaticCall(new FullyQualified(self::FACTORY), 'getContainer'),
                'get',
                [new Arg(new ClassConstFetch(new FullyQualified(self::CACHE_FACTORY_INTERFACE), 'class'))]
            );

            return new MethodCall($factory, 'createCacheController', $node->args);
        }

        return null;
    }

    /**
     * The namespaced classes are matched by their resolved name, the pre-namespace aliases by
     * their short name: inside a namespace an unimported `JSession` resolves to that namespace.
     */
    private function removedClassName(Name $class): ?string
    {
        $last = $class->getLast();

        if (\in_array($last, self::LEGACY_ALIASES, true)) {
            return $last;
        }

        $resolved = $this->getName($class);

        if ($resolved === null) {
            return null;
        }

        $resolved = ltrim($resolved, '\\');

        if (isset(self::APPLICATION_GETTERS[$resolved]) || \in_array($resolved, self::CACHE_CLASSES, true)) {
            return $resolved;
        }

        return null;
    }

    /**
     * The class must be shown to have getApplication(): an own method, a CMSPlugin ancestry or
     * anything reflection can confirm. Guessing would produce calls to a missing method.
     */
    private function hasApplicationGetter(Class_ $class): bool
    {
        if ($class->getMethod('getApplication') instanceof ClassMethod) {
            return true;
        }

        if (
            $class->extends !== null
            && (
                ltrim($class->extends->toString(), '\\') === self::PLUGIN_ANCESTOR
                || $class->extends->getLast() === 'CMSPlugin'
            )
        ) {
            return true;
        }

        $className = $this->getName($class);

        if ($className === null || !$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        return $this->reflectionProvider->getClass($className)->hasMethod('getApplication');
    }
}
