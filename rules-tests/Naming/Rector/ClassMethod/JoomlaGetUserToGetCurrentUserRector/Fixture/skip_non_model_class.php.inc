<?php

// Does NOT extend BaseModel — must not be changed.
class ExampleHelper
{
    public function getCurrentUser()
    {
        return Factory::getUser();
    }
}