<?php

// Does NOT extend a view class — must not be changed.
class ExampleModel
{
    public function display()
    {
        $this->assignRef('items', $this->items);
    }
}