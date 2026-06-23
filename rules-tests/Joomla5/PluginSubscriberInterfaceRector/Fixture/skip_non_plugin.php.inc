<?php

// Does NOT extend CMSPlugin — must not be changed.
class MyModel extends \Joomla\CMS\MVC\Model\BaseDatabaseModel
{
    public function getItems(): array
    {
        return [];
    }
}