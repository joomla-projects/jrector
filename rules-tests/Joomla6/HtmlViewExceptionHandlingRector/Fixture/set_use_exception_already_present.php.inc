<?php

// setUseExceptions already exists — only the if-block should be removed.
class ExampleHtmlView extends \Joomla\CMS\MVC\View\HtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $model->setUseExceptions(true);

        if (count($errors = $model->getErrors())) {
            throw new \Exception(implode("\n", $errors));
        }

        $items = $model->getItems();
    }
}
-----
<?php

// setUseExceptions already exists — only the if-block should be removed.
class ExampleHtmlView extends \Joomla\CMS\MVC\View\HtmlView
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $model->setUseExceptions(true);

        $items = $model->getItems();
    }
}
