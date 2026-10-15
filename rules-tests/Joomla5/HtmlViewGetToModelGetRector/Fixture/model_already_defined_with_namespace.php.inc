<?php

namespace Joomla\Rector\Tests\Joomla5\HtmlViewGetToModelGetRector\Source\View\Articles;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $items = $this->get('Items');
    }
}
-----
<?php

namespace Joomla\Rector\Tests\Joomla5\HtmlViewGetToModelGetRector\Source\View\Articles;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        /** @var \Joomla\Rector\Tests\Joomla5\HtmlViewGetToModelGetRector\Source\Model\ArticlesModel $model */
        $model = $this->getModel();
        $items = $model->getItems();
    }
}