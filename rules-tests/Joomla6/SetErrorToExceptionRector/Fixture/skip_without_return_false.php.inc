<?php

// setError without an immediately following return false — must not be changed.
class ExampleModel
{
    public function check()
    {
        $this->setError('Something went wrong');
        $this->doMoreStuff();
        return false;
    }
}
