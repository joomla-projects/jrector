<?php

namespace Acme\Component\Example\Site\View\Articles;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $items      = $this->get('Items');
        $pagination = $this->get('Pagination');
    }
}
-----
<?php

namespace Acme\Component\Example\Site\View\Articles;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        /** @var \Acme\Component\Example\Site\Model\ArticlesModel $model */
        $model = $this->getModel();
        $items      = $model->getItems();
        $pagination = $model->getPagination();
    }
}