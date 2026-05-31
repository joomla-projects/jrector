<?php

// No getErrors() if-block — only setUseException should be added.
class ExampleHtmlView extends HtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $items = $model->getItems();
    }
}
-----
<?php

// No getErrors() if-block — only setUseException should be added.
class ExampleHtmlView extends HtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $model->setUseException(true);
        $items = $model->getItems();
    }
}
