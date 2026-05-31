<?php

class ExampleHtmlView extends HtmlView
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

class ExampleHtmlView extends HtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $items = $model->getItems();
    }

    public function sidebar()
    {
        $model = $this->getModel();
        $featured = $model->getFeatured();
        $total    = $model->getTotal();
    }
}
