<?php

namespace Joomla\Rector\Tests\Joomla6\Template\CountModulesRector\Fixture;

// Not a document — an unrelated countModules() must not be rewritten.
class ModuleCounter
{
    public function run(): int
    {
        return $this->countModules('a and b');
    }

    public function countModules(string $expression): int
    {
        return 0;
    }
}
