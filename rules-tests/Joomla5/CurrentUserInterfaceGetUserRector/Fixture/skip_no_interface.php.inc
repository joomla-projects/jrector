<?php

use Joomla\CMS\Factory;

// Does NOT implement CurrentUserInterface — must not be changed.
class ExampleHelper
{
    public function getCurrentUserName(): string
    {
        return Factory::getUser()->name;
    }
}