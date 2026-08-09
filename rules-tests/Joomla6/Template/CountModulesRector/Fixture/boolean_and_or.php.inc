<?php

namespace Joomla\Rector\Tests\Joomla6\Template\CountModulesRector\Fixture;

class TemplateAndOr extends \Joomla\CMS\Document\HtmlDocument
{
    public function render($cache = false, $params = [])
    {
        if ($this->countModules('sidebar-left and sidebar-right')) {
            echo 'both';
        }

        if ($this->countModules('top-a or top-b')) {
            echo 'either';
        }

        if ($this->countModules('a  AND  b')) {
            echo 'case insensitive';
        }

        if ($this->countModules('p1 and p2 and p3')) {
            echo 'three';
        }
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Template\CountModulesRector\Fixture;

class TemplateAndOr extends \Joomla\CMS\Document\HtmlDocument
{
    public function render($cache = false, $params = [])
    {
        if ($this->countModules('sidebar-left') && $this->countModules('sidebar-right')) {
            echo 'both';
        }

        if ($this->countModules('top-a') || $this->countModules('top-b')) {
            echo 'either';
        }

        if ($this->countModules('a') && $this->countModules('b')) {
            echo 'case insensitive';
        }

        if ($this->countModules('p1') && $this->countModules('p2') && $this->countModules('p3')) {
            echo 'three';
        }
    }
}

?>
