<?php

class ExampleHtmlView extends HtmlView
{
    public function display($tpl = null)
    {
        $items      = $this->get('Items');
        $pagination = $this->get('Pagination');
    }
}
-----
<?php

class ExampleHtmlView extends HtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $items      = $model->getItems();
        $pagination = $model->getPagination();
    }
}
