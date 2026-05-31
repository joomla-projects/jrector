<?php

// Does NOT extend HtmlView — must not be changed.
class ExampleModel
{
    public function getItems()
    {
        $model = $this->getModel();

        if (count($errors = $model->getErrors())) {
            throw new \Exception(implode("\n", $errors));
        }

        return [];
    }
}