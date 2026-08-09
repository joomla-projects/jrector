<?php

namespace Joomla\Rector\Tests\Joomla6\Template\CountModulesRector\Fixture;

class TemplatePlus extends \Joomla\CMS\Document\HtmlDocument
{
    public function render($cache = false, $params = [])
    {
        $count = $this->countModules('top-a + top-b');

        return $count;
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Template\CountModulesRector\Fixture;

class TemplatePlus extends \Joomla\CMS\Document\HtmlDocument
{
    public function render($cache = false, $params = [])
    {
        $count = $this->countModules('top-a') + $this->countModules('top-b');

        return $count;
    }
}

?>
