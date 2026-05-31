<?php

class ExampleHtmlView extends HtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $items = $this->get('Items');
    }
}
-----
<?php

class ExampleHtmlView extends HtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $items = $model->getItems();
    }
}
