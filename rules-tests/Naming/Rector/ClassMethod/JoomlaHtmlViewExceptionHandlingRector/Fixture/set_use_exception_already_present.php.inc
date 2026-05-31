<?php

// setUseException already exists — only the if-block should be removed.
class ExampleHtmlView extends HtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $model->setUseException(true);

        if (count($errors = $model->getErrors())) {
            throw new \Exception(implode("\n", $errors));
        }

        $items = $model->getItems();
    }
}
-----
<?php

// setUseException already exists — only the if-block should be removed.
class ExampleHtmlView extends HtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $model->setUseException(true);

        $items = $model->getItems();
    }
}
