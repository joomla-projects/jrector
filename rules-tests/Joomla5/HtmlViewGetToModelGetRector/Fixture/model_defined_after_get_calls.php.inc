<?php

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $items = $this->get('Items');
        $model = $this->getModel();
    }
}
-----
<?php

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $items = $model->getItems();
    }
}
