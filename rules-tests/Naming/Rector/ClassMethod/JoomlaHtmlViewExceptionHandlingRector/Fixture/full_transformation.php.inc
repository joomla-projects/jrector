<?php

class ExampleHtmlView extends HtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();

        // Check for errors.
        if (count($errors = $model->getErrors())) {
            throw new \Exception(implode("\n", $errors));
        }

        $items = $model->getItems();
    }
}
-----
<?php

class ExampleHtmlView extends HtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();

        $model->setUseException(true);

        $items = $model->getItems();
    }
}
