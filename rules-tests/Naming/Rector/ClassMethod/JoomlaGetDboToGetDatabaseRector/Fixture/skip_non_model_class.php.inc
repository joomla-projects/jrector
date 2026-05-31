<?php

// Does NOT extend BaseDatabaseModel — must not be changed.
class ExampleHelper
{
    public function getDb()
    {
        return $this->getDbo();
    }
}