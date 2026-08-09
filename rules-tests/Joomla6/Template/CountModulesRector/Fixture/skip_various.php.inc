<?php

namespace Joomla\Rector\Tests\Joomla6\Template\CountModulesRector\Fixture;

class TemplateSkips extends \Joomla\CMS\Document\HtmlDocument
{
    public function render($cache = false, $params = [])
    {
        // Idempotence: a plain position name is already the supported form.
        $a = $this->countModules('sidebar-right');
        $b = $this->countModules('sidebar-right', true);

        // Mixed operators — the intended precedence is not recoverable.
        $c = $this->countModules('a and b or c');

        // Parentheses carry a precedence the rule must not guess at.
        $d = $this->countModules('(a and b) or c');

        // A non-literal argument cannot be parsed.
        $position = 'a and b';
        $e = $this->countModules($position);

        return [$a, $b, $c, $d, $e];
    }
}
