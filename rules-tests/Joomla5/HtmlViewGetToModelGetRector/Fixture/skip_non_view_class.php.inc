<?php

// This class does NOT extend HtmlView — should not be changed.
class ExampleModel
{
    public function getItems()
    {
        $data = $this->get('Items');

        return $data;
    }
}