<?php

namespace Joomla\Rector\Tests\Joomla5\HtmlViewGetToModelGetRector\Source\View\Articles;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $items = $this->get('Items');
        $form  = $this->get('Form');
    }

    public function sidebar()
    {
        $state = $this->get('State');
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
        $form  = $this->get('Form');
    }

    public function sidebar()
    {
        $state = $this->get('State');
    }
}
