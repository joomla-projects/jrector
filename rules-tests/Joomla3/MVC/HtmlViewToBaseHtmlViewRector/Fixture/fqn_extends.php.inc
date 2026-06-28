<?php

namespace Acme\Component\Example\Site\View\Example;

class DefaultView extends \Joomla\CMS\MVC\View\HtmlView
{
    public function display($tpl = null): void
    {
        parent::display($tpl);
    }
}

-----
<?php

namespace Acme\Component\Example\Site\View\Example;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
class DefaultView extends BaseHtmlView
{
    public function display($tpl = null): void
    {
        parent::display($tpl);
    }
}
