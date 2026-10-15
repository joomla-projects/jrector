<?php

namespace Joomla\Rector\Tests\Joomla5\HtmlViewGetToModelGetRector\Source\View\Articles;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $items = $this->get('Items');
    }

    public function sidebar()
    {
        $featured = $this->get('Featured');
        $total    = $this->get('Total');
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

    public function sidebar()
    {
        /** @var \Joomla\Rector\Tests\Joomla5\HtmlViewGetToModelGetRector\Source\Model\ArticlesModel $model */
        $model = $this->getModel();
        $featured = $model->getFeatured();
        $total    = $model->getTotal();
    }
}
