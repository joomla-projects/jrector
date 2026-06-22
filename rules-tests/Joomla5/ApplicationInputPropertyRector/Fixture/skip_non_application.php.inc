<?php

// $this->input is NOT on a variable from getApplication() — must not be changed.
class MyModel extends \Joomla\CMS\MVC\Model\BaseDatabaseModel
{
    public function getItems(): array
    {
        $input = $this->input;

        return [];
    }
}